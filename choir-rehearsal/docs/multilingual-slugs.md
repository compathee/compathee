# Multilingual song permalinks (all editions)

Applies to **Lite**, **Pro** (via Lite), **Demo**, and WordPress.org builds — one shared class: `includes/class-slugs.php`.

## Pipeline

1. Cyrillic map (RU + UK/BY extras); repairs WP `%d0%9f…` / hex-dump slugs  
2. `remove_accents()` for Latin diacritics (et, de, fr, …)  
3. PHP **`intl`** ICU `Any-Latin; Latin-ASCII` for Arabic, CJK, etc.  
4. If still empty → **`song-{post_id}`** (or `song` before an ID exists)

No per-language strategy classes. Pro add-on does not ship a separate slug module.

## Hosting

`php-intl` recommended for Arabic/CJK romanization. Without it, those titles keep the original script in the UI and get `song-{id}` in the URL.
