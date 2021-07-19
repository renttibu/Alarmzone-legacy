# Alarmzone  

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.  

Zur Verwendung dieses Moduls als Privatperson, Einrichter oder Integrator wenden Sie sich bitte zunächst an den Autor.

### Inhaltverzeichnis

1. [Ablaufplan](#Ablaufplan)
    1. [Alarmzone scharf schalten](#Alarmzone-scharf-schalten)
    2. [Alarmzone unscharf schalten](#Alarmzone-unscharf-schalten)

##

### Ablaufplan

Die nachfolgenden Punkte beschreiben den Ablauf bei Scharf- und Unscharfschaltung der Alarzome.

##

### Alarmzone scharf schalten

##

- [x] :clock8: Einschaltverzögerung

    - [x] Detailierter Alarmzonenstatus

        - [x] Tür- und Fensterstatus entspricht der Konfiguration

            - [x] :yellow_circle: Alarmzone: Verzögert Scharf

        - [ ] Tür- und Fensterstatus entspricht der Konfiguration

            - [x] :yellow_circle: Alarmzone: Verzögert Teilscharf

    - [ ] Detailierter Alarmzonenstatus

        - [x] :green_circle: Alarmzone: Unscharf

    - [x]  :warning: Scharfschaltung erfolgt nach definierter Verzögerung

##

- [x] :warning: Scharfschaltung

    - [x] :cop: Aktivierungsprüfung

        - [x] Tür- und Fensterstatus entspricht der Konfiguration

            - [x] Offene Türen und Fenster ohne Aktivierungsprüfung

                - [x] :no_entry_sign: Offene Türen und Fenster werden für die Auslösung gesperrt

                - [x] Detailierter Alarmzonenstatus

                    - [x] :yellow_circle: Alarmzone: Teilscharf

                - [ ] Detailierter Alarmzonenstatus

                    - [x] :red_circle: Alarmzone: Scharf

            - [ ] Offene Türen und Fenster ohne Aktivierungsprüfung

                - [x] :red_circle: Alarmzone: Scharf

        - [ ] Tür- und Fensterstatus entspricht der Konfiguration

            - [x] :heavy_exclamation_mark: Abbruch

            - [x] :green_circle: Alarmzone: Unscharf

    - [ ] :cop: Aktivierungsprüfung

        - [x] Detailierter Alarmzonenstatus

            - [x] Tür- und Fensterstatus entspricht der Konfiguration

                - [x] :red_circle: Alarmzone: Scharf

            - [ ] Tür- und Fensterstatus entspricht der Konfiguration

                - [x] :yellow_circle: Alarmzone: Teilscharf

                - [x] :no_entry_sign: Offene Türen und Fenster werden für die Auslösung gesperrt

        - [ ] Detailierter Alarmzonenstatus

            - [x] :red_circle: Alarmzone: Scharf

            - [ ] Tür- und Fensterstatus entspricht der Konfiguration

                - [x] :no_entry_sign: Offene Türen und Fenster werden für die Auslösung gesperrt

##

### Alarmzone unscharf schalten

##

- [x] :green_circle: Alarmzone: Unscharf

##