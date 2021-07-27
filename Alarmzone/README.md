# Alarmzone

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.

Zur Verwendung dieses Moduls als Privatperson, Einrichter oder Integrator wenden Sie sich bitte zunächst an den Autor.

### Inhaltverzeichnis

1. [Betriebsarten](#Betriebsarten)
    1. [Vollschutz](#Vollschutz)
    2. [Hüllschutz](#Hüllschutz)
    3. [Teilschutz](#Teilschutz)
2. [Alarmzonenstatus](#Alarmzonenstatus)
3. [Ablaufplan](#Ablaufplan)
    1. [Alarmzone scharf schalten](#Alarmzone-scharf-schalten)
    2. [Alarmzone unscharf schalten](#Alarmzone-unscharf-schalten)

##

### Betriebsarten

In den drei nachfolgenden Betriebsarten (Voll-, Hüll-, Teilschutz) können die Alarmsensoren (Tür- und Fenstersensoren, Bewegungsmelder) individuell zugewiesen werden.

##

### Vollschutz

Der Vollschutz umfasst in der Regel alle Alarmsensoren, d.h. es werden Tür- und Fenstersensoren, als auch Bewegungsmelder überwacht.

##

### Hüllschutz

Der Hüllschutz umfasst in der Regel nur Tür- und Fenstersensoren. Bewegungsmelder werden in der Regel nicht überwacht.

##

### Teilschutz

Der Teilschutz enthält eine individuelle Zuweisung von Tür- und Fenstersensoren und Bewegungsmeldern, welche überwacht werden sollen.

##

### Alarmzonenstatus

Status  | Bezeichnung           | Beschreibung
------- | --------------------- | ------------
0       | Unscharf              | Alarmzone ist unscharf
1       | Scharf                | Alarmzone ist scharf
2       | Verzögert Scharf      | Alarmzone wird verzögert scharf geschaltet
3       | Teilscharf            | Alarmzone ist teilscharf, es sind noch Türen oder Fenster geöffnet
4       | Verzögert Teilscharf  | Alarmzone wird verzögert scharf geschaltet, es sind noch Türen oder Fenster geöffnet


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