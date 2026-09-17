#!/usr/bin/env python3
"""Extract gettext strings and write et / ru_RU PO + l10n.php files."""

from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / "choir-rehearsal"
LANG_DIR = ROOT / "languages"
DOMAIN = "compath-choir-rehearsal"


def unesc(s: str) -> str:
    return (
        s.replace(r"\\", "\x00")
        .replace(r"\n", "\n")
        .replace(r"\t", "\t")
        .replace(r"\'", "'")
        .replace(r"\"", '"')
        .replace("\x00", "\\")
    )


def esc_po(s: str) -> str:
    return s.replace("\\", "\\\\").replace('"', '\\"').replace("\n", "\\n")


def extract_source_ids() -> dict[str, str | None]:
    """msgid -> plural msgid or None."""
    simple = re.compile(
        r"""(['"])((?:\\.|(?!\1).)*)\1\s*,\s*['"]compath-choir-rehearsal['"]"""
    )
    plural_re = re.compile(
        r"""_n(?:x)?\s*\(\s*(['"])((?:\\.|(?!\1).)*)\1\s*,\s*(['"])((?:\\.|(?!\3).)*)\3\s*,[^,]+,\s*['"]compath-choir-rehearsal['"]"""
    )
    all_ids: dict[str, str | None] = {}
    for path in ROOT.rglob("*.php"):
        if "tests" in path.parts or "languages" in path.parts:
            continue
        text = path.read_text(encoding="utf-8", errors="replace")
        for m in plural_re.finditer(text):
            one, many = unesc(m.group(2)), unesc(m.group(4))
            all_ids[one] = many
        for m in simple.finditer(text):
            s = unesc(m.group(2))
            if s and s not in all_ids:
                all_ids[s] = None
    return all_ids


def parse_po(path: Path) -> dict[str, object]:
    """Parse simple PO into msgid -> msgstr or list of plural forms."""
    text = path.read_text(encoding="utf-8")
    out: dict[str, object] = {}
    for ent in re.split(r"\n\n+", text):
        if 'msgid "' not in ent:
            continue
        mid = re.search(r'^msgid "(.*)"$', ent, re.M)
        if not mid:
            continue
        msgid = mid.group(1).replace("\\n", "\n").replace('\\"', '"')
        if msgid == "":
            continue
        mid_pl = re.search(r'^msgid_plural "(.*)"$', ent, re.M)
        if mid_pl:
            forms = re.findall(r'^msgstr\[(\d+)\] "(.*)"$', ent, re.M)
            out[msgid] = [v.replace("\\n", "\n").replace('\\"', '"') for _, v in sorted(forms, key=lambda x: int(x[0]))]
            out[f"__plural__:{msgid}"] = mid_pl.group(1).replace("\\n", "\n")
        else:
            mstr = re.search(r'^msgstr "(.*)"$', ent, re.M)
            if mstr:
                out[msgid] = mstr.group(1).replace("\\n", "\n").replace('\\"', '"')
    return out


# --- Estonian translations (et) ---
ET: dict[str, str] = {
    "Songs": "Laulud",
    "Song": "Laul",
    "Add Song": "Lisa laul",
    "Add New Song": "Lisa uus laul",
    "Edit Song": "Muuda laulu",
    "Choir Rehearsal": "Koori proovid",
    "Compath Choir Rehearsal": "Compath Choir Rehearsal",
    "Tracks": "Rajad",
    "Track": "Rada",
    "Backing track": "Saaterada",
    "Bass 1": "Bass 1",
    "Bass 2": "Bass 2",
    "Baritone 1": "Bariton 1",
    "Baritone 2": "Bariton 2",
    "Tenor 1": "Tenor 1",
    "Tenor 2": "Tenor 2",
    "Alto 1": "Alt 1",
    "Alto 2": "Alt 2",
    "Soprano 1": "Sopran 1",
    "Soprano 2": "Sopran 2",
    "Other": "Muu",
    "Voice Types": "Häälerühmad",
    "Voice Type": "Häälerühm",
    "Settings": "Seaded",
    "Choir Rehearsal Settings": "Koori proovide seaded",
    "Require login": "Nõua sisselogimist",
    "Only logged-in users can view rehearsal pages.": "Proovilehti näevad ainult sisselogitud kasutajad.",
    "Song list URL: %s": "Laulude loendi URL: %s",
    "Voice Tracks": "Häälerajad",
    "Add one row per voice part. Upload an MP3/WAV file or pick one from the Media Library.": "Lisa iga hääleosa jaoks rida. Laadi üles MP3/WAV või vali fail meediateegist.",
    "Voice": "Hääl",
    "Audio": "Audio",
    "Actions": "Tegevused",
    "No audio selected": "Audot ei ole valitud",
    "Upload / Select": "Laadi üles / Vali",
    "Remove": "Eemalda",
    "Add track": "Lisa rada",
    "Select audio": "Vali audio",
    "Use this audio": "Kasuta seda audot",
    "Rehearsal Library": "Proovide raamatukogu",
    "No songs yet.": "Laule veel ei ole.",
    "All songs": "Kõik laulud",
    "No tracks uploaded yet.": "Radu pole veel üles laaditud.",
    "Play": "Esita",
    "Pause": "Paus",
    "Seek": "Keri",
    "Now playing": "Praegu mängib",
    "Close player": "Sulge mängija",
    "PDF score attached": "PDF-partituur on lisatud",
    "Song not found.": "Laulu ei leitud.",
    "List choir songs": "Kuva koori laulud",
    "Returns published rehearsal songs with track counts.": "Tagastab avaldatud proovilaulud radade arvuga.",
    "Get choir song details": "Laulu üksikasjad",
    "Returns one song with all voice tracks and audio URLs.": "Tagastab ühe laulu kõigi hääleradade ja audio URL-idega.",
    "%1$s — %2$s": "%1$s — %2$s",
    "Score": "Partituur",
    "Previous": "Eelmine",
    "Next": "Järgmine",
    "Previous page": "Eelmine leht",
    "Next page": "Järgmine leht",
    "Expand PDF": "Laienda PDF",
    "Close full screen": "Sulge täisekraan",
    "Close": "Sulge",
    "Swipe to change pages · pinch to zoom": "Lehtede vahetamiseks libista · suumimiseks näpista",
    "No PDF selected yet.": "PDF-i pole veel valitud.",
    "Upload a PDF score for this song. Singers will see it with page navigation on the song page.": "Laadi sellele laulule PDF-partituur. Lauljad näevad seda lehel leheküljenavigatsiooniga.",
    "Upload / Select PDF": "Laadi üles / Vali PDF",
    "Remove PDF": "Eemalda PDF",
    "No PDF selected": "PDF-i ei ole valitud",
    "Select PDF": "Vali PDF",
    "Use this PDF": "Kasuta seda PDF-i",
    "Documentation": "Dokumentatsioon",
    "Product page, install guide, and changelog:": "Tooteleht, paigaldusjuhend ja muudatused:",
    "Open documentation": "Ava dokumentatsioon",
    "Plugin version": "Plugina versioon",
    "Installed version: %s": "Paigaldatud versioon: %s",
    "Edition": "Väljaanne",
    "Lite": "Lite",
    "Pro": "Pro",
    "Standard": "Standard",
    "Buy Pro": "Osta Pro",
    "Check for updates now": "Kontrolli uuendusi kohe",
    "Updates via WordPress.org": "Uuendused WordPress.org kaudu",
    "Update JSON URL": "Uuenduste JSON URL",
    "Leave empty to use the default GitHub Releases feed.": "Jäta tühjaks, et kasutada vaike GitHub Releases voogu.",
    "Load demo songs": "Laadi demolaulud",
    "Delete all songs": "Kustuta kõik laulud",
    "Yes, delete all songs": "Jah, kustuta kõik laulud",
    "Cancel": "Tühista",
    "Back to song list": "Tagasi laulude loendisse",
    "Make public": "Tee avalikuks",
    "Make private": "Tee privaatseks",
    "Public": "Avalik",
    "Private": "Privaatne",
    "Share": "Jaga",
    "Copy link": "Kopeeri link",
    "Link copied": "Link kopeeritud",
    "Open piano": "Ava klaver",
    "Piano": "Klaver",
    "Open metronome": "Ava metronoom",
    "Metronome": "Metronoom",
    "Start": "Start",
    "Stop": "Stopp",
    "BPM": "BPM",
    "Start recording": "Alusta salvestust",
    "Stop recording": "Peata salvestus",
    "Pause recording": "Pausi salvestus",
    "Resume recording": "Jätka salvestust",
    "Use recording": "Kasuta salvestust",
    "Cancel recording": "Tühista salvestus",
    "Recording…": "Salvestamine…",
    "Microphone recording": "Mikrofoniga salvestus",
    "Search songs": "Otsi laule",
    "Search": "Otsi",
    "No matching songs.": "Sobivaid laule ei leitud.",
    "Login": "Logi sisse",
    "Username or Email Address": "Kasutajanimi või e-posti aadress",
    "Password": "Parool",
    "Remember Me": "Jäta mind meelde",
    "Log In": "Logi sisse",
    "You must be logged in to view rehearsal pages.": "Proovilehtede vaatamiseks pead olema sisse loginud.",
    "Save": "Salvesta",
    "Update": "Uuenda",
    "Publish": "Avalda",
    "Yes": "Jah",
    "No": "Ei",
    "Missing": "Puudub",
    "Missing or unreadable": "Puudub või loetamatu",
    "Folder": "Kaust",
    "Version": "Versioon",
    "Plugin name": "Plugina nimi",
    "On disk": "Kettale",
    "Active": "Aktiivne",
    "file missing": "fail puudub",
    "Fix duplicate install": "Paranda topeltpaigaldus",
    "Fix duplicate Choir Rehearsal install": "Paranda Choir Rehearsal topeltpaigaldus",
    "Choir Rehearsal migration": "Choir Rehearsal migratsioon",
    "Open migration wizard": "Ava migratsiooni viisard",
    "Repair active plugins list": "Paranda aktiivsete pluginate nimekiri",
    "Repair active_plugins": "Paranda active_plugins",
    "Step 1 — Installs found": "1. samm — leitud paigaldused",
    "Step 2 — Choose folder to keep": "2. samm — vali alles jääv kaust",
    "Step 3 — Deactivate other copies": "3. samm — deaktiveeri teised koopiad",
    "Step 4 — Delete other folders": "4. samm — kustuta teised kaustad",
    "Step 5 — Activate kept copy": "5. samm — aktiveeri alles jäetud koopia",
    "Save choice": "Salvesta valik",
    "Deactivate other Lite plugins": "Deaktiveeri teised Lite pluginad",
    "Delete other folders": "Kustuta teised kaustad",
    "Activate kept Lite plugin": "Aktiveeri alles jäetud Lite plugin",
    "recommended for existing sites": "soovitatav olemasolevatele saitidele",
    "Only one Lite install was found on disk. No cleanup needed.": "Kettalt leiti ainult üks Lite paigaldus. Puhastust ei ole vaja.",
    "Migration finished. Only one Lite folder should remain. You can activate Pro now if needed.": "Migratsioon on valmis. Alles peaks jääma üks Lite kaust. Vajadusel saad nüüd Pro aktiveerida.",
    "active_plugins repaired. Reload this page (F5) so Lite and Pro load cleanly.": "active_plugins on parandatud. Laadi leht uuesti (F5), et Lite ja Pro laadiksid puhtalt.",
    "Sorry, you are not allowed to manage plugins.": "Sul ei ole õigust pluginaid hallata.",
    "Invalid keep folder.": "Sobimatu alles jääv kaust.",
    "Choose which folder to keep first.": "Vali esmalt, milline kaust alles jätta.",
    "Keep folder is missing. Re-upload Lite into choir-rehearsal/.": "Alles jäetav kaust puudub. Laadi Lite uuesti kausta choir-rehearsal/.",
    "Could not delete some folders (use FTP). ": "Mõnda kausta ei saanud kustutada (kasuta FTP-d). ",
    "Delete every Lite folder except the one you chose? Songs in the database will NOT be deleted.": "Kustutada kõik Lite kaustad peale valitu? Laulud andmebaasis EI kustutata.",
    "Turns off every Lite copy except the one you chose. Does not delete files.": "Lülitab välja kõik Lite koopiad peale valitu. Faile ei kustutata.",
    "Deletes extra plugin folders from disk only. Does not run uninstall and does not delete songs.": "Kustutab kettalt ainult lisakaustad. Uninstalli ei käivitata ja laule ei kustutata.",
    "Keeps choir-rehearsal/ (or the only folder on disk), removes ghost or duplicate Lite entries. Does not delete songs.": "Jätab choir-rehearsal/ (või ainsa kausta kettale), eemaldab kummituslikud või topelt Lite kirjed. Laule ei kustutata.",
    "Lite rows in active_plugins": "Lite read active_plugins nimekirjas",
    "After 0.4.39 the package folder was renamed for WordPress.org. Uploading a newer zip beside an existing choir-rehearsal/ folder creates a second copy. This wizard keeps one folder and removes the extras. Song data in the database is not deleted.": "Alates 0.4.39 nimetati paketi kaust WordPress.org jaoks ümber. Uue zip-i üleslaadimine olemasoleva choir-rehearsal/ kõrvale loob teise koopia. See viisard jätab ühe kausta ja eemaldab ülejäänud. Laulude andmeid andmebaasis ei kustutata.",
    "Only one Lite folder exists on disk, but WordPress still lists more than one Lite plugin as active (or a missing path). This breaks Pro and updates. Use the repair button below, then reload wp-admin.": "Kettale on ainult üks Lite kaust, aga WordPress peab aktiivseks rohkem kui üht Lite pluginat (või puuduvat teed). See rikub Pro ja uuendused. Kasuta allolevat parandusnuppu ja laadi wp-admin uuesti.",
    "Compath Choir Rehearsal is loaded from more than one folder. Open the migration wizard to keep one copy and remove the extras. Songs stay in the database.": "Compath Choir Rehearsal on laaditud rohkem kui ühest kaustast. Ava migratsiooni viisard, et jätta üks koopia ja eemaldada ülejäänud. Laulud jäävad andmebaasi.",
    "Multiple Compath Choir Rehearsal (Lite) folders were found. Use the migration wizard before relying on Pro or updates.": "Leiti mitu Compath Choir Rehearsal (Lite) kausta. Kasuta migratsiooni viisardit enne Pro või uuenduste kasutamist.",
    "Choir Rehearsal: duplicate Lite entries were removed from active plugins. Reload this page if Pro or features still look inactive.": "Choir Rehearsal: topelt Lite kirjed eemaldati aktiivsete pluginate nimekirjast. Laadi leht uuesti, kui Pro või funktsioonid tunduvad endiselt mitteaktiivsed.",
    "Upload a PDF score. It appears below like on the public song page — swipe left/right to change pages while you record.": "Laadi PDF-partituur. See kuvatakse allpool nagu avalikul lehel — salvestamise ajal saad lehti libistades vahetada.",
    "Upload a PDF score for this song. Singers will see it with page navigation on the song page. Pro embeds the score here in the editor.": "Laadi sellele laulule PDF-partituur. Lauljad näevad seda lehel navigatsiooniga. Pro manustab partituuri siia redaktorisse.",
    "Voice parts look like the public song page. Use the icons on the right to upload, record, or play.": "Hääleosad näevad välja nagu avalikul lehel. Kasuta paremal ikoone üleslaadimiseks, salvestamiseks või esitamiseks.",
    "Rehearsal page": "Proovide leht",
    "Page with the [choir_rehearsal] shortcode.": "Leht lühikoodiga [choir_rehearsal].",
    "— Select —": "— Vali —",
    # --- remaining EE strings ---
    "%d tracks": "%d rada",
    "Add one voice part per row (up to %1$d in Lite). Upload audio with the icon on the right. %2$s": "Lisa iga hääleosa jaoks rida (Lites kuni %1$d). Laadi audio üles parempoolse ikooniga. %2$s",
    "Add one voice part per row. Upload audio with the icon on the right.": "Lisa iga hääleosa jaoks rida. Laadi audio üles parempoolse ikooniga.",
    "Add song": "Lisa laul",
    "Add the first song": "Lisa esimene laul",
    "Add the song title, upload a PDF score, then record or upload each voice part.": "Lisa laulu pealkiri, laadi PDF-partituur ja seejärel salvesta või laadi üles iga hääleosa.",
    "Add the song title, upload a PDF score, then upload each voice part (up to 4 tracks in Lite).": "Lisa laulu pealkiri, laadi PDF-partituur ja seejärel laadi üles iga hääleosa (Lites kuni 4 rada).",
    "All songs will be deleted from the library. Do you agree?": "Kõik laulud kustutatakse raamatukogust. Kas oled nõus?",
    "An update is available for Compath Choir Rehearsal. Use Update now on the Plugins screen.": "Compath Choir Rehearsal’ile on uuendus saadaval. Kasuta Plugins-ekraanil nuppu Update now.",
    "Anyone can view and listen without signing in.": "Igaüks saab vaadata ja kuulata ilma sisselogimata.",
    "Appearance → Menus": "Välimus → Menüüd",
    "Check for plugin updates": "Kontrolli plugina uuendusi",
    "Choir Rehearsal Pro": "Choir Rehearsal Pro",
    "Click start and sing your voice part.": "Vajuta start ja laula oma hääleosa.",
    "Close metronome": "Sulge metronoom",
    "Close piano": "Sulge klaver",
    "Compath Choir Rehearsal is up to date (version %s).": "Compath Choir Rehearsal on ajakohane (versioon %s).",
    "Compath Choir Rehearsal is up to date.": "Compath Choir Rehearsal on ajakohane.",
    "Contacts GitHub Releases and shows the result on this page.": "Võtab ühendust GitHub Releases’iga ja näitab tulemust sellel lehel.",
    "Could not copy the link. Please copy it from the address bar.": "Linki ei õnnestunud kopeerida. Kopeeri see aadressiribalt.",
    "Could not create the demo audio file in the Media Library.": "Demo audiofaili ei õnnestunud meediateeki luua.",
    "Could not delete %s": "%s kustutamine ebaõnnestus",
    "Could not map update folder %1$s to installed folder %2$s.": "Uuenduskausta %1$s ei õnnestunud siduda paigaldatud kaustaga %2$s.",
    "Could not reach the update server. Check your connection or try again later. If this keeps happening, verify GitHub Releases are reachable from this site.": "Uuendusserveriga ei saanud ühendust. Kontrolli ühendust või proovi hiljem uuesti. Kui see kordub, veendu, et GitHub Releases on sellelt saidilt kättesaadav.",
    "Could not read plugin folder.": "Plugina kausta ei õnnestunud lugeda.",
    "Could not remove %s — delete via FTP.": "%s eemaldamine ebaõnnestus — kustuta FTP kaudu.",
    "Could not save the recording in the Media Library.": "Salvestust ei õnnestunud meediateeki salvestada.",
    "Deleted %1$d songs, %2$d tracks, and %3$d media files.": "Kustutatud %1$d laulu, %2$d rada ja %3$d meediafaili.",
    "Demo library": "Demo raamatukogu",
    "Edit": "Muuda",
    "Edit rehearsal page": "Muuda proovide lehte",
    "Edit song": "Muuda laulu",
    "Editor": "Toimetaja",
    "Forgot your password?": "Unustasid parooli?",
    "GitHub repository": "GitHubi hoidla",
    "Last check: %1$s — update available (%2$s).": "Viimane kontroll: %1$s — uuendus saadaval (%2$s).",
    "Last check: %s": "Viimane kontroll: %s",
    "Last check: %s — could not reach the update server.": "Viimane kontroll: %s — uuendusserveriga ei saanud ühendust.",
    "Last check: %s — up to date.": "Viimane kontroll: %s — ajakohane.",
    "Link copied to clipboard": "Link kopeeritud lõikelauale",
    "Lite edition allows up to %d voice tracks per song. Upgrade to Pro for unlimited tracks, microphone recording, Play preview, and embedded PDF in the editor.": "Lite võimaldab kuni %d häälerada laulu kohta. Pro avab piiramatu arvu radu, mikrofonisalvestuse, Play-eelvaate ja manustatud PDF-i redaktoris.",
    "Lite: up to %d voice tracks per song; no microphone recording, song search, editor Play, or embedded PDF preview.": "Lite: kuni %d häälerada laulu kohta; puuduvad mikrofonisalvestus, lauluotsing, redaktori Play ja manustatud PDF-eelvaade.",
    "Load %1$d sample songs (4 voice tracks each) so the public library shows pagination (%2$d songs per page), or wipe the whole rehearsal library.": "Laadi %1$d näidislaulu (igaüks 4 häälerajaga), et avalik raamatukogu näitaks lehekülgi (%2$d laulu lehel), või tühjenda kogu proovide raamatukogu.",
    "Loaded demo songs %1$d–%2$d (%3$d songs, %4$d tracks).": "Laaditud demolaulud %1$d–%2$d (%3$d laulu, %4$d rada).",
    "Manage library": "Halda raamatukogu",
    "Microphone access was denied.": "Mikrofoni ligipääs keelati.",
    "Microphone recording is available in Choir Rehearsal Pro.": "Mikrofoniga salvestus on saadaval Choir Rehearsal Pro-s.",
    "Microphone recording is not supported in this browser.": "See brauser ei toeta mikrofoniga salvestust.",
    "New Song": "Uus laul",
    "No public songs yet. Sign in below for the full library, or ask an editor to make a song public.": "Avalikke laule veel ei ole. Logi alla sisse täisraamatukogu jaoks või palu toimetajal teha laul avalikuks.",
    "No recording file was received.": "Salvestusfaili ei saadud.",
    "No recording was uploaded.": "Salvestust ei laaditud üles.",
    "No songs found in Trash.": "Prügis laule ei leitud.",
    "No songs found.": "Laule ei leitud.",
    "No songs match your search.": "Otsingule vastavaid laule ei ole.",
    "Only signed-in users can access this song (when login is required).": "Seda laulu näevad ainult sisselogitud kasutajad (kui sisselogimine on nõutud).",
    "Open Plugins": "Ava pluginad",
    "Optional override. If empty, the plugin checks GitHub Releases, then falls back to the latest release asset update.json.": "Valikuline ülekirjutus. Kui tühi, kontrollib plugin GitHub Releases’it ja langeb seejärel tagasi uusima release’i update.json varale.",
    "Paused": "Pausil",
    "Permalinks refreshed and rehearsal page verified.": "Püsiviited värskendatud ja proovide leht kontrollitud.",
    "Please enter your username and password.": "Sisesta kasutajanimi ja parool.",
    "Plugin documentation and changelog": "Plugina dokumentatsioon ja muudatused",
    "Product page: order, install, pricing, changelog": "Tooteleht: tellimine, paigaldus, hinnad, muudatused",
    "Public songs": "Avalikud laulud",
    "Public songs above are open to everyone. Sign in with your WordPress account to access the full rehearsal library.": "Ülal olevad avalikud laulud on kõigile avatud. Logi WordPressi kontoga sisse, et avada täielik proovide raamatukogu.",
    "Record": "Salvesta",
    "Recording is too large for the server upload limit.": "Salvestus ületab serveri üleslaadimise limiidi.",
    "Recording upload failed.": "Salvestuse üleslaadimine ebaõnnestus.",
    "Recording upload was blocked by the server.": "Server blokeeris salvestuse üleslaadimise.",
    "Recording was only partially uploaded.": "Salvestus laaditi üles ainult osaliselt.",
    "Refresh permalinks": "Värskenda püsiviiteid",
    "Refusing to delete that path.": "Selle tee kustutamisest keeldutakse.",
    "Remember me": "Jäta mind meelde",
    "Save the song first, then you can record voice tracks.": "Salvesta laul esmalt, seejärel saad hääleradasid salvestada.",
    "Search Songs": "Otsi laule",
    "Search by title…": "Otsi pealkirja järgi…",
    "Security check failed. Please try again.": "Turvakontroll ebaõnnestus. Proovi uuesti.",
    "Security check failed. Reload the page and try again.": "Turvakontroll ebaõnnestus. Laadi leht uuesti ja proovi uuesti.",
    "Sheet Music (PDF)": "Partituur (PDF)",
    "Sheet music": "Partituur",
    "Showing %1$d–%2$d of %3$d songs": "Kuvan %1$d–%2$d / %3$d laulu",
    "Sign in": "Logi sisse",
    "Sign in for the full library": "Logi sisse täisraamatukogu jaoks",
    "Sign in with your WordPress account to access songs, sheet music, and voice tracks.": "Logi WordPressi kontoga sisse, et avada laulud, partituurid ja häälerajad.",
    "Sign out": "Logi välja",
    "Signed in as": "Sisse logitud kui",
    "Singer": "Laulja",
    "Song list pages": "Laulude loendi lehed",
    "Sorry, you are not allowed to manage options.": "Sul ei ole õigust seadeid hallata.",
    "Sorry, you are not allowed to update plugins.": "Sul ei ole õigust pluginaid uuendada.",
    "Swipe left or right to change pages": "Lehtede vahetamiseks libista vasakule või paremale",
    "Tempo": "Tempo",
    "These songs are open to listen and view without signing in.": "Neid laule saab kuulata ja vaadata ilma sisselogimata.",
    "Two octave piano keyboard": "Kahe oktavi klaveriklaviatuur",
    "Unlimited tracks, microphone recording, search by song title, Play preview, and embedded PDF in the editor. Keep this Lite plugin installed — Pro is a separate add-on.": "Piiramatu arv radu, mikrofonisalvestus, otsing pealkirja järgi, Play-eelvaade ja manustatud PDF redaktoris. Hoia see Lite plugin paigaldatuna — Pro on eraldi lisand.",
    "Unlimited voice tracks, microphone recording, song search, Play preview, and embedded PDF in the editor.": "Piiramatu arv hääleradasid, mikrofonisalvestus, lauluotsing, Play-eelvaade ja manustatud PDF redaktoris.",
    "Update available: Compath Choir Rehearsal %s. Use Update now below or on the Plugins screen.": "Uuendus saadaval: Compath Choir Rehearsal %s. Kasuta allpool või Plugins-ekraanil nuppu Update now.",
    "Updates are delivered through WordPress.org.": "Uuendused tulevad WordPress.org kaudu.",
    "Updates are published at %s": "Uuendused avaldatakse aadressil %s",
    "Upload": "Laadi üles",
    "Upload failed. Please try again.": "Üleslaadimine ebaõnnestus. Proovi uuesti.",
    "Uploading…": "Üleslaadimine…",
    "Used when Update JSON URL is empty. Each Lite release should include compath-choir-rehearsal.zip and update.json assets.": "Kasutatakse, kui Update JSON URL on tühi. Iga Lite väljalaske juures peaksid olema failid compath-choir-rehearsal.zip ja update.json.",
    "Username or email": "Kasutajanimi või e-post",
    "View Song": "Vaata laulu",
    "Visibility": "Nähtavus",
    "Voice tracks": "Häälerajad",
    "WordPress page that shows the song list. Must contain the [choir_rehearsal] shortcode. You can add this page to your site menu under Appearance → Menus.": "WordPressi leht, mis näitab laulude loendit. Peab sisaldama lühikoodi [choir_rehearsal]. Saad lehe menüüsse lisada jaotises Välimus → Menüüd.",
    "You are not allowed to upload recordings for this song.": "Sul ei ole õigust selle laulu jaoks salvestusi üles laadida.",
    "You must sign in to view this song.": "Selle laulu vaatamiseks pead sisse logima.",
    "Your installed version is %1$s, but the update server only reported %2$s (likely stale metadata). Open Plugins and use Update now if shown, upload the latest zip, or set Update JSON URL to the latest GitHub release update.json.": "Paigaldatud versioon on %1$s, aga uuendusserver teatas ainult %2$s (tõenäoliselt vananenud metaandmed). Ava Plugins ja kasuta Update now, kui see on nähtav, laadi uusim zip või sea Update JSON URL uusima GitHub release’i update.json peale.",
    "choir-rehearsal": "choir-rehearsal",
}



# Russian additions for strings missing from older PO (beyond existing file)
RU_EXTRA: dict[str, str] = {
    "Play": "Играть",
    "Compath Choir Rehearsal": "Compath Choir Rehearsal",
    "Standard": "Стандарт",
    "Expand PDF": "Развернуть PDF",
    "Close full screen": "Закрыть полный экран",
    "Close": "Закрыть",
    "Swipe to change pages · pinch to zoom": "Свайп — смена страниц · щипок — масштаб",
    "No PDF selected yet.": "PDF пока не выбран.",
    "Open piano": "Открыть фортепиано",
    "Piano": "Фортепиано",
    "Open metronome": "Открыть метроном",
    "Metronome": "Метроном",
    "Start": "Старт",
    "Stop": "Стоп",
    "BPM": "BPM",
    "Start recording": "Начать запись",
    "Stop recording": "Остановить запись",
    "Pause recording": "Пауза записи",
    "Resume recording": "Продолжить запись",
    "Use recording": "Использовать запись",
    "Cancel recording": "Отменить запись",
    "Recording…": "Запись…",
    "Microphone recording": "Запись с микрофона",
    "Search songs": "Поиск песен",
    "Search": "Поиск",
    "No matching songs.": "Подходящих песен нет.",
    "Make public": "Сделать публичной",
    "Make private": "Сделать приватной",
    "Public": "Публичная",
    "Private": "Приватная",
    "Share": "Поделиться",
    "Copy link": "Копировать ссылку",
    "Link copied": "Ссылка скопирована",
    "Fix duplicate install": "Исправить двойную установку",
    "Fix duplicate Choir Rehearsal install": "Исправить двойную установку Choir Rehearsal",
    "Choir Rehearsal migration": "Миграция Choir Rehearsal",
    "Open migration wizard": "Открыть мастер миграции",
    "Repair active plugins list": "Исправить список активных плагинов",
    "Repair active_plugins": "Исправить active_plugins",
    "Step 1 — Installs found": "Шаг 1 — найденные установки",
    "Step 2 — Choose folder to keep": "Шаг 2 — выберите папку для сохранения",
    "Step 3 — Deactivate other copies": "Шаг 3 — деактивировать другие копии",
    "Step 4 — Delete other folders": "Шаг 4 — удалить другие папки",
    "Step 5 — Activate kept copy": "Шаг 5 — активировать сохранённую копию",
    "Save choice": "Сохранить выбор",
    "Deactivate other Lite plugins": "Деактивировать другие Lite-плагины",
    "Delete other folders": "Удалить другие папки",
    "Activate kept Lite plugin": "Активировать сохранённый Lite",
    "recommended for existing sites": "рекомендуется для существующих сайтов",
    "Only one Lite install was found on disk. No cleanup needed.": "На диске найдена только одна установка Lite. Очистка не нужна.",
    "Migration finished. Only one Lite folder should remain. You can activate Pro now if needed.": "Миграция завершена. Должна остаться одна папка Lite. При необходимости активируйте Pro.",
    "active_plugins repaired. Reload this page (F5) so Lite and Pro load cleanly.": "active_plugins исправлен. Обновите страницу (F5), чтобы Lite и Pro загрузились корректно.",
    "Sorry, you are not allowed to manage plugins.": "У вас нет прав на управление плагинами.",
    "Invalid keep folder.": "Некорректная папка для сохранения.",
    "Choose which folder to keep first.": "Сначала выберите, какую папку оставить.",
    "Keep folder is missing. Re-upload Lite into choir-rehearsal/.": "Папка для сохранения отсутствует. Загрузите Lite снова в choir-rehearsal/.",
    "Could not delete some folders (use FTP). ": "Не удалось удалить некоторые папки (используйте FTP). ",
    "Delete every Lite folder except the one you chose? Songs in the database will NOT be deleted.": "Удалить все папки Lite, кроме выбранной? Песни в базе данных НЕ будут удалены.",
    "Turns off every Lite copy except the one you chose. Does not delete files.": "Отключает все копии Lite, кроме выбранной. Файлы не удаляет.",
    "Deletes extra plugin folders from disk only. Does not run uninstall and does not delete songs.": "Удаляет с диска только лишние папки плагина. Не запускает uninstall и не удаляет песни.",
    "Keeps choir-rehearsal/ (or the only folder on disk), removes ghost or duplicate Lite entries. Does not delete songs.": "Оставляет choir-rehearsal/ (или единственную папку на диске), удаляет призрачные или дублирующие записи Lite. Песни не удаляет.",
    "Lite rows in active_plugins": "Записи Lite в active_plugins",
    "After 0.4.39 the package folder was renamed for WordPress.org. Uploading a newer zip beside an existing choir-rehearsal/ folder creates a second copy. This wizard keeps one folder and removes the extras. Song data in the database is not deleted.": "После 0.4.39 папка пакета переименована для WordPress.org. Загрузка нового zip рядом с choir-rehearsal/ создаёт вторую копию. Мастер оставляет одну папку и удаляет лишние. Данные песен в базе не удаляются.",
    "Only one Lite folder exists on disk, but WordPress still lists more than one Lite plugin as active (or a missing path). This breaks Pro and updates. Use the repair button below, then reload wp-admin.": "На диске одна папка Lite, но WordPress считает активными несколько записей Lite (или путь отсутствует). Это ломает Pro и обновления. Нажмите кнопку исправления ниже и обновите wp-admin.",
    "Compath Choir Rehearsal is loaded from more than one folder. Open the migration wizard to keep one copy and remove the extras. Songs stay in the database.": "Compath Choir Rehearsal загружен из нескольких папок. Откройте мастер миграции, чтобы оставить одну копию и удалить лишние. Песни остаются в базе.",
    "Multiple Compath Choir Rehearsal (Lite) folders were found. Use the migration wizard before relying on Pro or updates.": "Найдено несколько папок Compath Choir Rehearsal (Lite). Используйте мастер миграции перед работой с Pro или обновлениями.",
    "Choir Rehearsal: duplicate Lite entries were removed from active plugins. Reload this page if Pro or features still look inactive.": "Choir Rehearsal: дублирующие записи Lite удалены из активных плагинов. Обновите страницу, если Pro или функции всё ещё неактивны.",
    "Yes": "Да",
    "No": "Нет",
    "Missing": "Нет",
    "Missing or unreadable": "Отсутствует или нечитаем",
    "Folder": "Папка",
    "Version": "Версия",
    "Plugin name": "Имя плагина",
    "On disk": "На диске",
    "Active": "Активен",
    "file missing": "файл отсутствует",
    "Back to song list": "Назад к списку песен",
    "Documentation": "Документация",
    "Open documentation": "Открыть документацию",
    "Plugin version": "Версия плагина",
    "Installed version: %s": "Установленная версия: %s",
    "Edition": "Редакция",
    "Buy Pro": "Купить Pro",
    "Check for updates now": "Проверить обновления",
    "Updates via WordPress.org": "Обновления через WordPress.org",
    "Load demo songs": "Загрузить демо-песни",
    "Delete all songs": "Удалить все песни",
    "Yes, delete all songs": "Да, удалить все песни",
    "Cancel": "Отмена",
    "Save": "Сохранить",
    "Update": "Обновить",
    "Publish": "Опубликовать",
    "Login": "Вход",
    "Log In": "Войти",
    "Username or Email Address": "Имя пользователя или email",
    "Password": "Пароль",
    "Remember Me": "Запомнить меня",
    "You must be logged in to view rehearsal pages.": "Для просмотра страниц репетиций нужно войти.",
    "Rehearsal page": "Страница репетиций",
    "Page with the [choir_rehearsal] shortcode.": "Страница с шорткодом [choir_rehearsal].",
    "— Select —": "— Выбрать —",
    "Upload a PDF score. It appears below like on the public song page — swipe left/right to change pages while you record.": "Загрузите PDF-партитуру. Она отображается ниже, как на публичной странице — листайте страницы свайпом во время записи.",
    "Upload a PDF score for this song. Singers will see it with page navigation on the song page. Pro embeds the score here in the editor.": "Загрузите PDF-партитуру. Певцы увидят её с навигацией на странице песни. Pro встраивает партитуру здесь в редакторе.",
    "Voice parts look like the public song page. Use the icons on the right to upload, record, or play.": "Партии выглядят как на публичной странице. Иконки справа — загрузка, запись или воспроизведение.",
    # --- remaining RU strings ---
    "Sorry, you are not allowed to update plugins.": "У вас нет прав на обновление плагинов.",
    "Update available: Compath Choir Rehearsal %s. Use Update now below or on the Plugins screen.": "Доступно обновление: Compath Choir Rehearsal %s. Нажмите «Обновить сейчас» ниже или на экране «Плагины».",
    "An update is available for Compath Choir Rehearsal. Use Update now on the Plugins screen.": "Для Compath Choir Rehearsal доступно обновление. Нажмите «Обновить сейчас» на экране «Плагины».",
    "Your installed version is %1$s, but the update server only reported %2$s (likely stale metadata). Open Plugins and use Update now if shown, upload the latest zip, or set Update JSON URL to the latest GitHub release update.json.": "Установлена версия %1$s, а сервер обновлений сообщил только %2$s (вероятно устаревшие метаданные). Откройте «Плагины» и нажмите «Обновить сейчас», загрузите актуальный zip или укажите Update JSON URL на update.json последнего GitHub release.",
    "Open Plugins": "Открыть «Плагины»",
    "Compath Choir Rehearsal is up to date (version %s).": "Compath Choir Rehearsal актуален (версия %s).",
    "Compath Choir Rehearsal is up to date.": "Compath Choir Rehearsal актуален.",
    "Could not reach the update server. Check your connection or try again later. If this keeps happening, verify GitHub Releases are reachable from this site.": "Не удалось связаться с сервером обновлений. Проверьте соединение или повторите позже. Если ошибка повторяется, убедитесь, что GitHub Releases доступны с этого сайта.",
    "Microphone recording is available in Choir Rehearsal Pro.": "Запись с микрофона доступна в Choir Rehearsal Pro.",
    "You are not allowed to upload recordings for this song.": "У вас нет прав загружать записи для этой песни.",
    "No recording was uploaded.": "Запись не была загружена.",
    "Could not save the recording in the Media Library.": "Не удалось сохранить запись в медиатеке.",
    "Recording upload failed.": "Не удалось загрузить запись.",
    "Plugin documentation and changelog": "Документация и журнал изменений плагина",
    "Lite: up to %d voice tracks per song; no microphone recording, song search, editor Play, or embedded PDF preview.": "Lite: до %d голосовых дорожек на песню; без записи с микрофона, поиска песен, Play в редакторе и встроенного PDF.",
    "Unlimited voice tracks, microphone recording, song search, Play preview, and embedded PDF in the editor.": "Неограниченные голосовые дорожки, запись с микрофона, поиск песен, Play-превью и встроенный PDF в редакторе.",
    "Updates are delivered through WordPress.org.": "Обновления доставляются через WordPress.org.",
    "Last check: %1$s — update available (%2$s).": "Последняя проверка: %1$s — доступно обновление (%2$s).",
    "Last check: %s — up to date.": "Последняя проверка: %s — актуально.",
    "Last check: %s — could not reach the update server.": "Последняя проверка: %s — сервер обновлений недоступен.",
    "Last check: %s": "Последняя проверка: %s",
    "Update JSON URL": "URL JSON обновлений",
    "Optional override. If empty, the plugin checks GitHub Releases, then falls back to the latest release asset update.json.": "Необязательное переопределение. Если пусто, плагин проверяет GitHub Releases, затем берёт update.json из последнего релиза.",
    "GitHub repository": "Репозиторий GitHub",
    "Used when Update JSON URL is empty. Each Lite release should include compath-choir-rehearsal.zip and update.json assets.": "Используется, когда Update JSON URL пуст. Каждый Lite-релиз должен включать файлы compath-choir-rehearsal.zip и update.json.",
    "Choir Rehearsal Pro": "Choir Rehearsal Pro",
    "Unlimited tracks, microphone recording, search by song title, Play preview, and embedded PDF in the editor. Keep this Lite plugin installed — Pro is a separate add-on.": "Неограниченные дорожки, запись с микрофона, поиск по названию, Play-превью и встроенный PDF в редакторе. Оставьте Lite установленным — Pro это отдельное дополнение.",
    "Check for plugin updates": "Проверить обновления плагина",
    "Contacts GitHub Releases and shows the result on this page.": "Обращается к GitHub Releases и показывает результат на этой странице.",
    "Visibility": "Видимость",
    "Add the song title, upload a PDF score, then upload each voice part (up to 4 tracks in Lite).": "Добавьте название, загрузите PDF-партитуру, затем каждую партию (до 4 дорожек в Lite).",
    "Anyone can view and listen without signing in.": "Смотреть и слушать можно без входа.",
    "Only signed-in users can access this song (when login is required).": "Доступ только для вошедших пользователей (если требуется вход).",
    "Close piano": "Закрыть фортепиано",
    "Close metronome": "Закрыть метроном",
    "Upload": "Загрузить",
    "Swipe left or right to change pages": "Свайп влево или вправо для смены страниц",
    "Paused": "Пауза",
    "Lite edition allows up to %d voice tracks per song. Upgrade to Pro for unlimited tracks, microphone recording, Play preview, and embedded PDF in the editor.": "В Lite до %d голосовых дорожек на песню. Pro даёт неограниченные дорожки, запись с микрофона, Play-превью и встроенный PDF в редакторе.",
    "Add one voice part per row (up to %1$d in Lite). Upload audio with the icon on the right. %2$s": "Добавьте по одной партии в строке (в Lite до %1$d). Загрузите аудио иконкой справа. %2$s",
    "Add one voice part per row. Upload audio with the icon on the right.": "Добавьте по одной партии в строке. Загрузите аудио иконкой справа.",
    "Sorry, you are not allowed to manage options.": "У вас нет прав на управление настройками.",
    "You must sign in to view this song.": "Чтобы увидеть эту песню, нужно войти.",
    "Pro": "Pro",
    "Lite": "Lite",
    "New Song": "Новая песня",
    "View Song": "Смотреть песню",
    "Search Songs": "Поиск песен",
    "No songs found.": "Песни не найдены.",
    "No songs found in Trash.": "В корзине песен нет.",
    "%d tracks": "%d треков",
    "Link copied to clipboard": "Ссылка скопирована в буфер",
    "Could not copy the link. Please copy it from the address bar.": "Не удалось скопировать ссылку. Скопируйте её из адресной строки.",
    "Sign in for the full library": "Войдите для полной библиотеки",
    "Public songs above are open to everyone. Sign in with your WordPress account to access the full rehearsal library.": "Публичные песни выше открыты всем. Войдите через WordPress, чтобы открыть полную библиотеку репетиций.",
    "Public songs": "Публичные песни",
    "Search by title…": "Поиск по названию…",
    "No public songs yet. Sign in below for the full library, or ask an editor to make a song public.": "Публичных песен пока нет. Войдите ниже для полной библиотеки или попросите редактора сделать песню публичной.",
    "These songs are open to listen and view without signing in.": "Эти песни можно слушать и смотреть без входа.",
    "No songs match your search.": "Поиск не дал результатов.",
    "Song list pages": "Страницы списка песен",
    "Showing %1$d–%2$d of %3$d songs": "Показано %1$d–%2$d из %3$d песен",
    "Two octave piano keyboard": "Клавиатура фортепиано на две октавы",
    "Tempo": "Темп",
    "choir-rehearsal": "choir-rehearsal",
    "Could not map update folder %1$s to installed folder %2$s.": "Не удалось сопоставить папку обновления %1$s с установленной папкой %2$s.",
    "Refusing to delete that path.": "Отказ удалять этот путь.",
    "Could not read plugin folder.": "Не удалось прочитать папку плагина.",
    "Could not delete %s": "Не удалось удалить %s",
    "Could not remove %s — delete via FTP.": "Не удалось удалить %s — удалите через FTP.",
    "Product page: order, install, pricing, changelog": "Страница продукта: заказ, установка, цены, журнал изменений",
    "Updates are published at %s": "Обновления публикуются по адресу %s",
    "Refresh permalinks": "Обновить постоянные ссылки",
    "Edit rehearsal page": "Редактировать страницу репетиций",
    "Appearance → Menus": "Внешний вид → Меню",
    "Permalinks refreshed and rehearsal page verified.": "Постоянные ссылки обновлены, страница репетиций проверена.",
    "No recording file was received.": "Файл записи не получен.",
    "Recording upload was blocked by the server.": "Сервер заблокировал загрузку записи.",
    "Recording is too large for the server upload limit.": "Запись слишком большая для лимита загрузки сервера.",
    "Recording was only partially uploaded.": "Запись загружена только частично.",
    "Security check failed. Reload the page and try again.": "Проверка безопасности не пройдена. Обновите страницу и попробуйте снова.",
    "Security check failed. Please try again.": "Проверка безопасности не пройдена. Попробуйте снова.",
    "Microphone access was denied.": "Доступ к микрофону запрещён.",
    "Microphone recording is not supported in this browser.": "Этот браузер не поддерживает запись с микрофона.",
    "Upload failed. Please try again.": "Загрузка не удалась. Попробуйте снова.",
    "Uploading…": "Загрузка…",
    "Record": "Запись",
    "Click start and sing your voice part.": "Нажмите старт и спойте свою партию.",
    "Save the song first, then you can record voice tracks.": "Сначала сохраните песню, затем можно записывать партии.",
    "Could not create the demo audio file in the Media Library.": "Не удалось создать демо-аудиофайл в медиатеке.",
    "Deleted %1$d songs, %2$d tracks, and %3$d media files.": "Удалено %1$d песен, %2$d дорожек и %3$d медиафайлов.",
    "Demo library": "Демо-библиотека",
    "Load %1$d sample songs (4 voice tracks each) so the public library shows pagination (%2$d songs per page), or wipe the whole rehearsal library.": "Загрузить %1$d примерных песен (по 4 дорожки), чтобы публичная библиотека показала постраничность (%2$d на странице), или очистить всю библиотеку репетиций.",
    "Loaded demo songs %1$d–%2$d (%3$d songs, %4$d tracks).": "Загружены демо-песни %1$d–%2$d (%3$d песен, %4$d дорожек).",
    "All songs will be deleted from the library. Do you agree?": "Все песни будут удалены из библиотеки. Согласны?",
    "Manage library": "Управление библиотекой",
    "Add song": "Добавить песню",
    "Add the first song": "Добавить первую песню",
    "Add the song title, upload a PDF score, then record or upload each voice part.": "Добавьте название, загрузите PDF-партитуру, затем запишите или загрузите каждую партию.",
    "Edit": "Изменить",
    "Edit song": "Изменить песню",
    "Editor": "Редактор",
    "Sheet Music (PDF)": "Партитура (PDF)",
    "Sheet music": "Партитура",
    "Voice tracks": "Голосовые дорожки",
    "Forgot your password?": "Забыли пароль?",
    "Please enter your username and password.": "Введите имя пользователя и пароль.",
    "Remember me": "Запомнить меня",
    "Sign in": "Войти",
    "Sign out": "Выйти",
    "Signed in as": "Вы вошли как",
    "Singer": "Певец",
    "Username or email": "Имя пользователя или email",
    "WordPress page that shows the song list. Must contain the [choir_rehearsal] shortcode. You can add this page to your site menu under Appearance → Menus.": "Страница WordPress со списком песен. Должна содержать шорткод [choir_rehearsal]. Добавьте её в меню: Внешний вид → Меню.",
}


ET_PLURAL = {
    "%d track": ("%d rada", "%d rada"),  # Estonian: nplurals=2; plural=(n != 1);
}

RU_PLURAL = {
    "%d track": ("%d трек", "%d трека", "%d треков"),
}


def write_po(path: Path, locale: str, translations: dict[str, object], source_ids: dict[str, str | None], plural_forms: str) -> None:
    lines = [
        'msgid ""',
        'msgstr ""',
        f'"Project-Id-Version: Compath Choir Rehearsal 0.4.53\\n"',
        f'"Language: {locale}\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        f'"X-Domain: {DOMAIN}\\n"',
        f'"Plural-Forms: {plural_forms}\\n"',
        "",
    ]
    for msgid in sorted(source_ids.keys(), key=lambda s: s.lower()):
        plural = source_ids[msgid]
        val = translations.get(msgid)
        if plural:
            # plural entry
            lines.append(f'msgid "{esc_po(msgid)}"')
            lines.append(f'msgid_plural "{esc_po(plural)}"')
            if isinstance(val, (list, tuple)):
                for i, form in enumerate(val):
                    lines.append(f'msgstr[{i}] "{esc_po(str(form))}"')
            else:
                # fallback: duplicate singular
                n = 2 if "nplurals=2" in plural_forms else 3
                base = str(val) if val else msgid
                for i in range(n):
                    lines.append(f'msgstr[{i}] "{esc_po(base)}"')
            lines.append("")
        else:
            msgstr = str(val) if val is not None else ""
            lines.append(f'msgid "{esc_po(msgid)}"')
            lines.append(f'msgstr "{esc_po(msgstr)}"')
            lines.append("")
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def write_l10n_php(path: Path, locale: str, translations: dict[str, object]) -> None:
    messages: dict[str, object] = {}
    for k, v in translations.items():
        if k.startswith("__plural__:"):
            continue
        if isinstance(v, (list, tuple)):
            messages[k] = list(v)
        elif v:
            messages[k] = v
    # PHP export
    def php_str(s: str) -> str:
        return "'" + s.replace("\\", "\\\\").replace("'", "\\'") + "'"

    lines = ["<?php", "return [", f"    'domain' => '{DOMAIN}',", f"    'locale' => '{locale}',", "    'messages' => ["]
    for k in sorted(messages.keys(), key=lambda s: s.lower()):
        v = messages[k]
        if isinstance(v, list):
            arr = ", ".join(php_str(x) for x in v)
            lines.append(f"        {php_str(k)} => [{arr}],")
        else:
            lines.append(f"        {php_str(k)} => {php_str(str(v))},")
    lines += ["    ],", "];", ""]
    path.write_text("\n".join(lines), encoding="utf-8")


def main() -> None:
    source_ids = extract_source_ids()
    # Ensure known plurals
    source_ids["%d track"] = "%d tracks"

    ru_existing = parse_po(LANG_DIR / "compath-choir-rehearsal-ru_RU.po")
    ru_map: dict[str, object] = {}
    for msgid, plural in source_ids.items():
        if plural:
            if msgid in RU_PLURAL:
                ru_map[msgid] = list(RU_PLURAL[msgid])
            elif isinstance(ru_existing.get(msgid), list):
                ru_map[msgid] = ru_existing[msgid]
            else:
                ru_map[msgid] = [msgid, plural, plural]
        else:
            if msgid in RU_EXTRA:
                ru_map[msgid] = RU_EXTRA[msgid]
            elif msgid in ru_existing and isinstance(ru_existing[msgid], str):
                ru_map[msgid] = ru_existing[msgid]
            else:
                ru_map[msgid] = RU_EXTRA.get(msgid, "")

    et_map: dict[str, object] = {}
    for msgid, plural in source_ids.items():
        if plural:
            if msgid in ET_PLURAL:
                et_map[msgid] = list(ET_PLURAL[msgid])
            else:
                # leave empty for review if unknown
                one = ET.get(msgid, "")
                et_map[msgid] = [one or msgid, one or plural]
        else:
            et_map[msgid] = ET.get(msgid, "")

    # Fill remaining ET with empty string already; print coverage
    et_done = sum(1 for v in et_map.values() if (isinstance(v, list) and all(v)) or (isinstance(v, str) and v))
    print(f"source={len(source_ids)} et_translated={et_done} ru_translated={sum(1 for v in ru_map.values() if v)}")

    # WordPress locale for Estonian is `et` (also write et_EE alias files).
    write_po(
        LANG_DIR / f"{DOMAIN}-et.po",
        "et",
        et_map,
        source_ids,
        "nplurals=2; plural=(n != 1);",
    )
    write_po(
        LANG_DIR / f"{DOMAIN}-et_EE.po",
        "et_EE",
        et_map,
        source_ids,
        "nplurals=2; plural=(n != 1);",
    )
    write_po(
        LANG_DIR / f"{DOMAIN}-ru_RU.po",
        "ru_RU",
        ru_map,
        source_ids,
        "nplurals=3; plural=(n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2);",
    )

    write_l10n_php(LANG_DIR / f"{DOMAIN}-et.l10n.php", "et", et_map)
    write_l10n_php(LANG_DIR / f"{DOMAIN}-et_EE.l10n.php", "et_EE", et_map)
    write_l10n_php(LANG_DIR / f"{DOMAIN}-ru_RU.l10n.php", "ru_RU", ru_map)

    # Report untranslated ET
    missing_et = [k for k, v in et_map.items() if (isinstance(v, str) and not v) or (isinstance(v, list) and not all(v))]
    print("et missing", len(missing_et))
    for k in missing_et[:40]:
        print("  ", k[:100])
    Path("/tmp/et-missing.json").write_text(json.dumps(missing_et, ensure_ascii=False, indent=2), encoding="utf-8")

    # Compile .mo for older WordPress (WP 6.5+ prefers .l10n.php).
    import shutil
    import subprocess

    msgfmt = shutil.which("msgfmt")
    if msgfmt:
        for po in LANG_DIR.glob(f"{DOMAIN}-*.po"):
            mo = po.with_suffix(".mo")
            subprocess.run([msgfmt, "-o", str(mo), str(po)], check=True)
            print("mo", mo.name)
    else:
        print("msgfmt not found; skipped .mo compile")


if __name__ == "__main__":
    main()
