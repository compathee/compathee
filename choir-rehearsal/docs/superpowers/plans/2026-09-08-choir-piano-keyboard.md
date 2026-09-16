# Choir Piano Keyboard Implementation Plan

> **For agentic workers:** implement task-by-task; commit after working slices.

**Goal:** Ship a scrollable 2-octave Web Audio piano panel toggled from the sticky player.

**Architecture:** Markup on sticky player + `piano.js` (AudioContext synth, pointer notes, open/close) + CSS panel above player. Track playback unchanged.

**Tech Stack:** Vanilla JS, Web Audio API, existing WordPress enqueue.

## Global Constraints

- Version bump Lite + Pro together
- No new npm deps / no sample files
- Public + admin song editor share the same player markup

---

### Task 1: Markup + enqueue

- [ ] Extend `render_sticky_player()` with piano toggle + sheet panel
- [ ] Enqueue `piano.js` on public + admin (with player)
- [ ] Localize open/close i18n strings

### Task 2: `piano.js` + CSS

- [ ] Build C3–B4 keyboard DOM
- [ ] Web Audio e-piano voice; pointer on/off; multi-pointer
- [ ] Horizontal scroll strip; panel open/close above player
- [ ] Styles for keys, sheet, player icon

### Task 3: Release hygiene

- [ ] Bump 0.4.29, changelog, fixture test, zip/PR
