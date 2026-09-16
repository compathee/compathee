# Choir tools — piano keyboard (v1)

Date: 2026-09-08  
Status: approved to implement (keyboard first)

## Goal

Add a simple 2-octave on-screen piano to Choir Rehearsal as the first item in a future “chorister tools” set (keyboard → metronome → …).

## v1 scope

- Toggle from an icon on the sticky player
- Panel slides up **above** the sticky player (player stays visible; track keeps playing)
- Close with ×
- 2 octaves (C3–B4), white + black keys
- Play with mouse / touch (pointer events); multi-touch when possible
- Horizontally scrollable with finger or mouse drag / wheel
- One electric-piano-like timbre via **Web Audio** (no sample files)
- Works on public song page and song editor (same sticky player markup)

## Out of scope (later)

- Metronome, headphone gate, QWERTY mapping, instrument picker, recording integration rules beyond “track keeps playing”

## UX

```
[Play] [==== waveform / seek ====] [piano] [time] [×]
─────────────────────────────────────────────────────
│ Piano                                      [×]    │
│ [◀ scrollable 2-octave keyboard ▶]                │
─────────────────────────────────────────────────────
```

- Piano button only useful when player chrome exists; panel can open even if no track is loaded (optional: still show button whenever sticky player is in DOM — show when player is open).
- Opening piano does **not** pause the track.
- Closing piano stops sounding notes; does not close the player.

## Technical design

- Markup: extend `Choir_Rehearsal_Frontend::render_sticky_player()` with piano toggle + `#choir-piano-sheet` panel
- `public/js/piano.js`: AudioContext (`latencyHint: 'interactive'`), note on/off, pointer handling, scroll container
- Styles in `public/css/public.css` (admin reuses public player styles)
- Enqueue `piano.js` next to `player.js`

### Sound

- Per note: triangle + soft sine, lowpass, short attack / medium decay ADSR
- Frequency: `440 * 2^((midi-69)/12)` for MIDI 48–71 (C3–B4)

### Scroll

- `.choir-piano-sheet__keys-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }`
- Keys sized so 2 octaves wider than typical phone → natural swipe scroll
- Pointer on keys: `touch-action: none` on key elements so vertical press doesn’t fight scroll; horizontal gestures on empty padding / scrollbar still scroll. Prefer wrapping whites in a row and using `touch-action: pan-x` on the scroll container with key `pointerdown` only when not clearly a pan — simplest reliable approach: scroll container `overflow-x: auto`, keys use pointer capture for press, horizontal swipe on non-key chrome or native scroll on the strip.

## Future hooks

- Panel shell named for tools (`choir-tools-sheet`) later; v1 uses `choir-piano-sheet`
- Shared AudioContext can later feed metronome clicks
