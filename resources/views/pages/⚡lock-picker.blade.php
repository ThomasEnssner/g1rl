<?php

use App\Gothic\Lock;
use App\Gothic\LockCodec;
use App\Gothic\Move;
use App\Gothic\Solver;
use App\Gothic\State;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::public')] #[Title('Gothic 1 Remake Lockpicker')] class extends Component {
    /**
     * URL-safe serialization of the whole lock definition. Kept in the
     * query string so a lock can be shared as a plain link.
     */
    #[Url(as: 'lock', except: '')]
    public string $lockCode = '';

    public string $startPins = '';

    /**
     * One delta row per plate position: row 0 is P1, row 1 is P2, and so on.
     * Deltas describe the ">" direction; "<" is always the exact mirror and
     * derived automatically. An all-zero row means "not observed yet".
     *
     * @var list<array<int, int|string>>
     */
    public array $moves = [];

    public int $observationPosition = 1;

    public string $observationDirection = '>';

    public string $observationBefore = '';

    public string $observationAfter = '';

    /** @var list<string> */
    public array $solutionMoves = [];

    /**
     * Every state along the solution, including start and solved state.
     * Drives the client-side playback animation.
     *
     * @var list<list<int>>
     */
    public array $solutionStates = [];

    public bool $hasResult = false;

    public bool $solvable = false;

    public int $visitedStates = 0;

    public int $iterations = 0;

    public float $durationMs = 0.0;

    public function mount(): void
    {
        if ($this->lockCode === '') {
            return;
        }

        try {
            ['startPins' => $this->startPins, 'rows' => $rows] = LockCodec::decode($this->lockCode);

            $this->moves = $this->normalizeRows($rows);
            $this->observationBefore = $this->startPins;
            $this->suggestObservation();
        } catch (InvalidArgumentException) {
            $this->reset('lockCode', 'startPins', 'moves');
        }
    }

    public function updatedStartPins(): void
    {
        $this->resetResult();

        if ($this->pinCount() > 0) {
            $this->moves = $this->normalizeRows($this->moves);
            $this->observationBefore = trim($this->startPins);
            $this->suggestObservation();
        } else {
            $this->moves = [];
        }

        $this->syncLockCode();
    }

    public function updatedMoves(): void
    {
        $this->resetResult();
        $this->syncLockCode();
    }

    public function discoverMove(): void
    {
        $this->resetResult();

        $this->validate([
            'observationPosition' => ['required', 'integer'],
            'observationDirection' => ['required', 'in:<,>'],
            'observationBefore' => ['required', 'regex:/^[1-7]{4,6}$/'],
            'observationAfter' => ['required', 'regex:/^[1-7]{4,6}$/'],
        ], [
            'observationBefore.regex' => __('States must be 4-6 digits (one per plate), each between 1 and 7.'),
            'observationAfter.regex' => __('States must be 4-6 digits (one per plate), each between 1 and 7.'),
        ]);

        if ($this->observationPosition < 1 || $this->observationPosition > $this->pinCount()) {
            $this->addError('observationPosition', __('Pick a valid plate position.'));

            return;
        }

        if (strlen($this->observationBefore) !== $this->pinCount()) {
            $this->addError('observationBefore', __('The observed states must have :count plates, like the start state.', ['count' => $this->pinCount()]));

            return;
        }

        try {
            $observed = Move::fromObservation(
                'P'.$this->observationPosition.$this->observationDirection,
                State::fromString($this->observationBefore),
                State::fromString($this->observationAfter),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('observationAfter', $exception->getMessage());

            return;
        }

        // Plausibility check: "Pn>" always raises plate n by exactly 1,
        // "Pn<" always lowers it by exactly 1. Anything else means the
        // position, direction or one of the states is wrong.
        $expectedOwnDelta = $this->observationDirection === '>' ? 1 : -1;

        if ($observed->delta[$this->observationPosition - 1] !== $expectedOwnDelta) {
            $this->addError('observationAfter', __('Plausibility check failed: :move must move plate :position by :delta. Check the position, direction and both states.', [
                'move' => $observed->name,
                'position' => $this->observationPosition,
                'delta' => $expectedOwnDelta,
            ]));

            return;
        }

        // Rows always store the ">" direction - mirror if "<" was observed.
        if ($this->observationDirection === '<') {
            $observed = $observed->inverted($observed->name);
        }

        $this->moves[$this->observationPosition - 1] = $observed->delta;

        // The lock is now in the "after" state - chain the next observation from there.
        $this->observationBefore = $this->observationAfter;
        $this->reset('observationAfter', 'observationDirection');
        $this->suggestObservation();

        $this->syncLockCode();
    }

    public function solve(): void
    {
        $this->resetResult();

        $this->validate([
            'startPins' => ['required', 'regex:/^[1-7]{4,6}$/'],
            'moves' => ['required', 'array', 'min:1'],
            'moves.*' => ['required', 'array'],
            'moves.*.*' => ['required', 'integer', 'between:-1,1'],
        ], [
            'startPins.regex' => __('A lock has 4-6 plates: enter one digit (1-7) per plate.'),
            'moves.*.*.required' => __('Every plate needs a delta value.'),
            'moves.*.*.between' => __('A plate can only move by -1, 0 or 1.'),
        ]);

        try {
            $domainMoves = [];

            foreach ($this->moves as $index => $delta) {
                $delta = array_map(intval(...), array_values($delta));

                if (array_filter($delta) === []) {
                    continue; // Not observed yet - an all-zero move cannot help anyway.
                }

                if (($delta[$index] ?? 0) !== 1) {
                    $this->addError('moves', __('P:number> must raise plate :number by exactly 1 - fix the highlighted delta or clear the whole row.', ['number' => $index + 1]));

                    return;
                }

                $move = new Move('P'.($index + 1).'>', $delta);

                $domainMoves[] = $move;
                $domainMoves[] = $move->inverted('P'.($index + 1).'<');
            }

            if ($domainMoves === []) {
                $this->addError('moves', __('Define at least one move - observe the lock or enter deltas manually.'));

                return;
            }

            $lock = new Lock(State::fromString($this->startPins), $domainMoves);
        } catch (InvalidArgumentException $exception) {
            $this->addError('moves', $exception->getMessage());

            return;
        }

        $startedAt = hrtime(true);
        $solution = new Solver()->solve($lock);
        $this->durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 2);

        $this->hasResult = true;
        $this->solvable = $solution->isSolvable();
        $this->solutionMoves = $solution->moveNames();
        $this->visitedStates = $solution->visitedStates;
        $this->iterations = $solution->iterations;

        if ($solution->isSolvable()) {
            $this->solutionStates = array_map(
                static fn (State $state): array => $state->pins,
                $solution->replay($lock->startState),
            );
        }
    }

    public function pinCount(): int
    {
        $pins = trim($this->startPins);

        return ctype_digit($pins) ? strlen($pins) : 0;
    }

    private function resetResult(): void
    {
        $this->reset('hasResult', 'solvable', 'solutionMoves', 'solutionStates', 'visitedStates', 'iterations', 'durationMs');
        $this->resetErrorBag();
    }

    /**
     * Fits the delta rows to the current plate count: one row per plate,
     * each row padded or truncated to one delta per plate.
     *
     * @param  list<array<int, int|string>>  $rows
     * @return list<list<int>>
     */
    private function normalizeRows(array $rows): array
    {
        $count = $this->pinCount();

        $rows = array_map(
            fn (array $delta): array => $this->resizeDelta($delta),
            array_slice(array_values($rows), 0, $count),
        );

        while (count($rows) < $count) {
            $rows[] = array_fill(0, $count, 0);
        }

        return $rows;
    }

    /**
     * Suggests the next observation: the first position without deltas yet,
     * pushing ">" first - unless that plate already sits at 7, where "<" is
     * the only direction left.
     */
    private function suggestObservation(): void
    {
        foreach ($this->moves as $index => $delta) {
            if (array_filter(array_map(intval(...), $delta)) !== []) {
                continue;
            }

            $before = trim($this->observationBefore);
            $plate = strlen($before) === $this->pinCount() ? (int) $before[$index] : 0;

            $this->observationPosition = $index + 1;
            $this->observationDirection = $plate === State::MAX_PIN ? '<' : '>';

            return;
        }
    }

    /**
     * Keeps the shareable lock code in the URL in sync with the current
     * definition. Incomplete definitions simply clear the code.
     */
    private function syncLockCode(): void
    {
        try {
            $this->lockCode = LockCodec::encode(
                trim($this->startPins),
                array_map(static fn (array $delta): array => array_map(intval(...), array_values($delta)), $this->moves),
            );
        } catch (InvalidArgumentException) {
            $this->lockCode = '';
        }
    }

    /**
     * @param  array<int, int|string>  $delta
     * @return list<int>
     */
    private function resizeDelta(array $delta): array
    {
        $delta = array_map(intval(...), array_values($delta));

        return array_slice(array_pad($delta, $this->pinCount(), 0), 0, $this->pinCount());
    }
};
?>

<div class="mx-auto flex w-full max-w-5xl flex-col gap-10 px-6 py-12">
    <header>
        <flux:heading size="xl" level="1">Gothic 1 Remake Lockpicker</flux:heading>
        <flux:text class="mt-2 max-w-2xl">
            {{ __('Enter the start position of the lock and its movement rules. The solver runs a breadth first search and always finds the shortest sequence that moves every pin to 4.') }}
        </flux:text>
    </header>

    <section class="flex flex-col gap-4">
        <flux:heading size="lg" level="2">{{ __('Start state') }}</flux:heading>

        <flux:field class="max-w-xs">
            <flux:label>{{ __('Pin positions') }}</flux:label>
            <flux:input wire:model.live.debounce.500ms="startPins" placeholder="747317" class="font-mono" />
            <flux:description>{{ __('One digit (1-7) per plate, 4-6 plates, e.g. 747317. Target is the middle position 4 for every plate.') }}</flux:description>
            <flux:error name="startPins" />
        </flux:field>
    </section>

    <section class="flex flex-col gap-4">
        <div>
            <flux:heading size="lg" level="2">{{ __('Moves') }}</flux:heading>
            <flux:text size="sm" class="mt-1">
                {{ __('One move per plate position. Deltas describe the ">" direction - the "<" direction always mirrors it and is added automatically. Pn> always raises plate n itself by 1 (highlighted cell).') }}
            </flux:text>
        </div>

        <flux:error name="moves" />

        @if ($moves === [])
            <div class="rounded-lg border border-dashed border-zinc-300 p-6 text-center dark:border-zinc-600">
                <flux:text>{{ __('Enter the start state first - the moves P1-P6 will be set up automatically.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Move') }}</flux:table.column>
                    @for ($pin = 1; $pin <= $this->pinCount(); $pin++)
                        <flux:table.column>{{ __('Plate :number', ['number' => $pin]) }}</flux:table.column>
                    @endfor
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($moves as $index => $delta)
                        <flux:table.row wire:key="move-{{ $index }}">
                            <flux:table.cell>
                                <span class="font-mono font-semibold">P{{ $index + 1 }}</span>
                            </flux:table.cell>

                            @foreach ($delta as $pin => $value)
                                <flux:table.cell wire:key="move-{{ $index }}-pin-{{ $pin }}" class="{{ $index === $pin ? 'bg-amber-500/10' : '' }}">
                                    <flux:input
                                        type="number"
                                        min="-1"
                                        max="1"
                                        wire:model.change="moves.{{ $index }}.{{ $pin }}"
                                        size="sm"
                                        class="max-w-20"
                                    />
                                </flux:table.cell>
                            @endforeach
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            <div class="flex flex-col gap-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <div>
                    <flux:heading size="md" level="3">{{ __('Discover a move') }}</flux:heading>
                    <flux:text size="sm" class="mt-1">
                        {{ __('Try a move in the game and enter the pin positions before and after - the deltas are computed for you. The "before" field follows along, so you can chain observations.') }}
                    </flux:text>
                </div>

                <div class="flex flex-wrap items-start gap-4">
                    <flux:field>
                        <flux:label>{{ __('Position') }}</flux:label>
                        <flux:select wire:model="observationPosition" size="sm" class="max-w-24">
                            @for ($position = 1; $position <= $this->pinCount(); $position++)
                                <flux:select.option value="{{ $position }}">P{{ $position }}</flux:select.option>
                            @endfor
                        </flux:select>
                        <flux:error name="observationPosition" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Direction') }}</flux:label>
                        <flux:select wire:model="observationDirection" size="sm" class="max-w-20">
                            <flux:select.option value=">">&gt;</flux:select.option>
                            <flux:select.option value="<">&lt;</flux:select.option>
                        </flux:select>
                        <flux:error name="observationDirection" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Before') }}</flux:label>
                        <flux:input wire:model="observationBefore" placeholder="747317" size="sm" class="max-w-32 font-mono" />
                        <flux:error name="observationBefore" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('After') }}</flux:label>
                        <flux:input wire:model="observationAfter" placeholder="647327" size="sm" class="max-w-32 font-mono" />
                        <flux:error name="observationAfter" />
                    </flux:field>

                    <flux:button icon="sparkles" size="sm" wire:click="discoverMove" class="mt-6">
                        {{ __('Add from observation') }}
                    </flux:button>
                </div>
            </div>
        @endif
    </section>

    <section class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center gap-3">
            <flux:button variant="primary" icon="key" wire:click="solve">
                {{ __('Solve') }}
            </flux:button>

            @if ($lockCode !== '')
                <div x-data="{ copied: false }">
                    <flux:button
                        variant="ghost"
                        icon="link"
                        x-on:click="
                            navigator.clipboard.writeText(window.location.origin + window.location.pathname + '?lock=' + encodeURIComponent($wire.lockCode));
                            copied = true;
                            setTimeout(() => copied = false, 2000);
                        "
                    >
                        <span x-show="! copied">{{ __('Copy share link') }}</span>
                        <span x-show="copied" style="display: none;">{{ __('Link copied!') }}</span>
                    </flux:button>
                </div>
            @endif
        </div>

        @if ($hasResult)
            @if ($solvable)
                <div class="flex flex-col gap-4">
                    <flux:heading size="lg" level="2">{{ __('Solution') }}</flux:heading>

                    @if ($solutionMoves === [])
                        <flux:callout icon="check-circle" variant="success">
                            <flux:callout.text>{{ __('The lock is already open - every pin starts at 4.') }}</flux:callout.text>
                        </flux:callout>
                    @else
                        <flux:text>
                            {{ trans_choice('{1} Shortest solution: :count move.|[2,*] Shortest solution: :count moves.', count($solutionMoves), ['count' => count($solutionMoves)]) }}
                        </flux:text>

                        <div class="flex flex-wrap items-center gap-2">
                            @foreach ($solutionMoves as $step => $name)
                                <flux:badge size="lg" wire:key="solution-{{ $step }}">
                                    <span class="me-1.5 text-xs text-zinc-400">{{ $step + 1 }}.</span>
                                    <span class="font-mono">{{ $name }}</span>
                                </flux:badge>
                            @endforeach
                        </div>

                        <div
                            wire:key="player-{{ md5(json_encode($solutionStates)) }}"
                            x-data="{
                                states: @js($solutionStates),
                                moves: @js($solutionMoves),
                                step: 0,
                                playing: false,
                                timer: null,
                                get current() { return this.states[this.step] },
                                get done() { return this.step >= this.states.length - 1 },
                                play() {
                                    if (this.done) { this.step = 0 }
                                    this.playing = true
                                    this.timer = setInterval(() => { this.done ? this.pause() : this.step++ }, 700)
                                },
                                pause() { this.playing = false; clearInterval(this.timer) },
                                stepForward() { this.pause(); if (! this.done) this.step++ },
                                stepBack() { this.pause(); if (this.step > 0) this.step-- },
                                restart() { this.pause(); this.step = 0 },
                            }"
                            class="flex flex-col gap-6 rounded-lg border border-zinc-200 p-6 dark:border-zinc-700"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:button size="sm" icon="play" x-show="! playing" x-on:click="play">{{ __('Play') }}</flux:button>
                                <flux:button size="sm" icon="pause" x-on:click="pause" x-show="playing" style="display: none;">{{ __('Pause') }}</flux:button>
                                <flux:button size="sm" icon="chevron-left" x-on:click="stepBack" aria-label="{{ __('Previous step') }}" />
                                <flux:button size="sm" icon="chevron-right" x-on:click="stepForward" aria-label="{{ __('Next step') }}" />
                                <flux:button size="sm" icon="arrow-path" x-on:click="restart" aria-label="{{ __('Restart') }}" />

                                <flux:text class="ms-2 tabular-nums">
                                    <span x-text="step"></span>/<span x-text="states.length - 1"></span>
                                    <span class="ms-2 font-mono font-semibold" x-text="step === 0 ? @js(__('Start')) : moves[step - 1]"></span>
                                </flux:text>
                            </div>

                            <div class="flex items-start justify-center gap-4 sm:gap-6">
                                <template x-for="(value, plate) in current" :key="plate">
                                    <div class="flex flex-col items-center gap-2">
                                        <div class="relative h-44 w-9 rounded-md bg-zinc-100 dark:bg-zinc-700/40">
                                            {{-- Target slot: the middle position 4. --}}
                                            <div
                                                class="absolute inset-x-0 border-y border-dashed border-amber-500/70 bg-amber-500/10"
                                                style="top: calc(3 * 100% / 7); height: calc(100% / 7)"
                                            ></div>

                                            {{-- The pin, sliding to its current position. --}}
                                            <div
                                                class="absolute inset-x-1 flex items-center justify-center rounded bg-blue-500 text-xs font-semibold text-white transition-[top] duration-500 ease-in-out"
                                                style="height: calc(100% / 7 - 2px)"
                                                :style="{ top: `calc(${7 - value} * 100% / 7 + 1px)` }"
                                            >
                                                <span x-text="value"></span>
                                            </div>
                                        </div>
                                        <flux:text size="sm">{{ __('Plate') }} <span x-text="plate + 1"></span></flux:text>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <flux:callout icon="exclamation-triangle" variant="warning">
                    <flux:callout.heading>{{ __('This lock is impossible') }}</flux:callout.heading>
                    <flux:callout.text>{{ __('The solver explored every reachable state, but none of them opens the lock.') }}</flux:callout.text>
                </flux:callout>
            @endif

            <div class="flex flex-col gap-4">
                <flux:heading size="lg" level="2">{{ __('Statistics') }}</flux:heading>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text size="sm">{{ __('Visited states') }}</flux:text>
                        <flux:heading size="lg">{{ number_format($visitedStates) }}</flux:heading>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text size="sm">{{ __('Iterations') }}</flux:text>
                        <flux:heading size="lg">{{ number_format($iterations) }}</flux:heading>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:text size="sm">{{ __('Duration') }}</flux:text>
                        <flux:heading size="lg">{{ $durationMs }} ms</flux:heading>
                    </div>
                </div>
            </div>
        @endif
    </section>
</div>
