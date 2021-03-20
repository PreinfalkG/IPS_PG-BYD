# BYD_HVS_HMS_Premium_Battery
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

*

### 2. Vorraussetzungen

- IP-Symcon ab Version 5.3

### 3. Software-Installation

* Über den Module Store das 'BYD_HVS_HMS_Premium_Battery'-Modul installieren.
* Alternativ über das Module Control folgende URL hinzufügen

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'BYD_HVS_HMS_Premium_Battery'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
         |
         |

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
       |         |
       |         |

#### Profile

Name   | Typ
------ | -------
       |
       |

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

`boolean BYD_BeispielFunktion(integer $InstanzID);`
Erklärung der Funktion.

Beispiel:
`BYD_BeispielFunktion(12345);`

https://www.photovoltaikforum.com/thread/143728-byd-b-box-premium-hvs-7-7-sma-sbs-2-5/?postID=2091049#post2091049

Sowohl die Anfragen als auch die Antworten beginnen immer mit \x0103, keine Ahnung was das zu bedeuten hat, vielleicht bedeutet das Turm 1 von 3 oder der chinesische Programmierer hatte einfach am ersten März Geburtstag. Die letzten beiden Bytes im Paket sind eine CRC16 Checksumme.
Warum man nun also die Bytes \x00000013 und \x05000019 zum BYD schicken muss weiß der Teufel, zumindest antwortet er darauf mit Paketen die die von mir gewünschten Infos enthalten. Wer es weiß oder herausfindet kann es mir ja sagen.

Im Antwortpaket (blau) kommt nach dem \x0103 erstmal ein Byte mit der Länge der Nutzdaten. Damit weiß der Empfänger wie viele Bytes er vom Netzwerkstream lesen muss. Danach kommen dann noch zusätzlich die 2 Bytes für den CRC.

ACHTUNG: der CRC geht über die Nutzdaten PLUS das davorstehende Längenbyte.

Das erste Antwortpaket hat demnach 0x26 (=38) Byte. Die ersten 19 Bytes sind die Seriennummer, schwarz gefärbt. Danach kommen 5 "x"e. Danach jeweils 2 Byte für die Versionsnummern von BMU-A, BMU-B und BMS. Den Inhalt der verbliebenen 8 Byte konnte ich nicht ermitteln.

Das zweite Antwortpaket hat 0x32 (50 Bytes). Es besteht aus jeweils 2 Bytes für SoC, CellVoltageHigh + Low, SoH, Strom, Vbatt, TempHigh, TempLow und dann weiter hinten kommt nochmal Vout (in obigem Beispiel 0xa4a6). Die restlichen Bytes haben sich mir noch nicht erschlossen. Einige der Werte sind schon ok, andere müssen durch 100 geteilt werden , damit sich dann ein sinnvoller Wert mit Nachkommastellen ergibt, z.B: die Spannung. Der Wert für den Strom ist "signed", d.h. der kann beim Laden der Batterie negativ werden. Ob und wie das genau funktioniert sehe ich erst morgen wenn er wieder lädt.



https://www.photovoltaikforum.com/thread/143728-byd-b-box-premium-hvs-7-7-sma-sbs-2-5/?postID=2126507#post2126507 
https://www.photovoltaikforum.com/user-post-list/126139-frakur/
