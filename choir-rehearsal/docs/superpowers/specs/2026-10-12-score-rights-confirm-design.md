# Design: PDF score rights confirmation (local library)

**Date:** 2026-10-12  
**Status:** Approved for implementation (product request)  
**Related:** copyright memo §0.1 / U0, WP-22 §3.18.9

## Goal

Before attaching a PDF score in the song editor, the client must **confirm** they have rights to use the work for rehearsal on their site, **or** that the work is in the public domain. Confirmation is a blocking step (not a passive warning).

## Scope

* Song editor metabox **Sheet music** → Upload / Select PDF.
* Applies to both new upload and picking an existing media library PDF.
* Audio / voice-track uploads are out of scope.

## UX

1. User clicks **Upload / Select PDF**.
2. Modal shows rights statement + **I confirm** / **Cancel**.
3. Only **I confirm** opens the WordPress media frame.
4. Cancel closes the modal; no media frame.

## Server

* Hidden field `choir_score_rights_confirmed` = `1` after confirm in this edit session.
* On save: if the PDF attachment id **changes** to a new non-zero id, require confirmation; otherwise keep existing score.
* Store `_choir_score_rights_confirmed_at` (unix time) and `_choir_score_rights_confirmed_by` (user id) when a new PDF is accepted.
* Removing a PDF does not require confirmation.

## Copy (EN source strings)

* Title: `Confirm rights to this score`
* Body: `I confirm that I have the rights to use this musical work for rehearsal on this site, or that the work is in the public domain.`
* Confirm button: `I confirm`
* Cancel: `Cancel`
