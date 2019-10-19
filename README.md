# Bewerberüberischt
Das Plugin erstellt eine Liste aller Bewerber mit einer Frist wie lange sie noch Zeit haben für die Bewerbung. Jeder Bewerber kann seine Frist selber verlängern und Teammitglieder haben die Möglichkeit den Steckbrief für die Korrektur zu übernehmen. Dies geschieht entweder über einen Button auf der neu angelegten PHP-Seite oder über einen optisch identischen Button hinter dem Beitrag im Steckbriefbereich. Optional wird der Bewerber per PN über die Übernahme informiert.
Bei einer auslaufenden Frist wird der Bewerber über einen Banner informiert.

## Funktionen
* Übersicht in einer Tabelle wann die Frist von welchem Bewerber ausläuft
* Möglichkeit für Teammitglieder und Bewerber die Frist zu verlängern
* Banner bei drohender endener Frist und abgelaufener Frist (Bewerber)
* Banner bei ausgelaufenen Fristen (Team)
* Teammitglieder können Steckbriefe mit einem Klick übernehmen
* Anzeige in Tabelle, wenn ein Steckbrief übernommen wurde sowie unter dem Steckbrief-Thread
* Möglichkeit Bewerber über Übernahme zu informieren (optional)
* Anzeige der Frist in der Bewerber-Checklist von sparks fly (falls installiert)

## Voraussetzungen
* FontAwesome muss eingebunden sein, andernfalls muss man die Icons in den PHP-Datein ersetzen

## Template-Änderungen
__Neue globale Templates:__
* applicants
* applicantsHeader
* applicantsHeaderTeam
* applicantsUser

__Veränderte Templates:__
* header (wird um die Variable {$header_applicants} erweitert)
* forumdisplay_thread (wird um die Variablen {$correctionButton} und {$corrector} erweitert)
* checklist (falls Plugin von Sparky Fly installiert ist)

## Vorschaubilder
__Einstellungen des Bewerberübersicht-Plugin__
![Bewerberübersicht Einstellungen](https://beforestorm.de/imageUpload/plugins/applicants_settings.png)

__Bewerberübersichtseite__
![Bewerberübersichtseite](http://beforestorm.de/imageUpload/plugins/applicants_overview.png)

__Ansicht des Bewerberbereichs__
![Ansicht des Bewerberbereichs](hhttp://beforestorm.de/imageUpload/plugins/applicants_thread.png)
