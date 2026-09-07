#!/bin/sh

# Intercom - postroot
#
# Laeuft als ROOT und nach postinstall/postupgrade (plugininstall.pl ruft es
# ohne sudo-Praefix auf; die Reihenfolge ist Cron kopieren, postinstall,
# postupgrade, postroot).
#
# Er hat genau eine Aufgabe: die alte cron.d-Datei entfernen.
#
# ---------------------------------------------------------------------------
# SONST LAUFEN DIE AUFTRAEGE DOPPELT.
#
# Bis 2.2.7 lieferte diese Linie ihre Auftraege als cron/crontab aus. Der
# Installateur legt daraus system/cron/cron.d/<NAME> an und entfernt sie
# ausschliesslich beim DEINSTALLIEREN (plugininstall.pl:1571, der Kommentar
# dort sagt "only on uninstall"). Seit 2.2.8 kommen dieselben Auftraege aus
# cron/cron.01min und cron/cron.daily - ohne diesen Schritt liefe der
# Zeitraffer nach dem Upgrade ZWEIMAL je Minute.
#
# WARUM NICHT IN postupgrade.sh: dort kann es nicht gelingen. Am 07.09.2026
# an der laufenden Anlage gemessen: postupgrade laeuft per
# "sudo -n -u loxberry", der Ordner system/cron/cron.d ist
# drwxrwxr-x root root, und loxberry ist nicht in der Gruppe root -
# "sudo -u loxberry touch .../cron.d/.probe" endet mit Permission denied.
# Wer im VERZEICHNIS nicht schreiben darf, kann darin auch nichts loeschen.
#
# Der Dateiname ist der Plugin-NAME ($2), nicht der Ordner ($3).
#
# Geprueft wird die WIRKUNG, nicht der Rueckgabewert - doppelt laufende
# Cron-Auftraege bemerkt niemand von selbst.
# ---------------------------------------------------------------------------

ARGV2=$2   # Plugin-NAME
ARGV5=$5   # Basisordner von LoxBerry

if [ "$(id -u)" != "0" ]; then
	echo "<ERROR> postroot.sh muss als root laufen."
	exit 2
fi

if [ -n "$ARGV2" ] && [ -n "$ARGV5" ]; then
	ALT_CRON="$ARGV5/system/cron/cron.d/$ARGV2"
	if [ -e "$ALT_CRON" ]; then
		echo "<INFO> Entferne die alte cron.d-Datei aus der Zeit vor 2.2.8: $ALT_CRON"
		rm -f "$ALT_CRON"
		if [ -e "$ALT_CRON" ]; then
			echo "<WARNING> Die alte cron.d-Datei liess sich NICHT entfernen."
			echo "<WARNING> Bis das geschehen ist, laufen die Auftraege DOPPELT."
			echo "<WARNING> Bitte einmal von Hand ausfuehren:"
			echo "<WARNING>   sudo rm -f $ALT_CRON"
		else
			echo "<OK> Alte cron.d-Datei entfernt; die Auftraege laufen jetzt einfach."
		fi
	else
		echo "<INFO> Keine alte cron.d-Datei vorhanden - nichts zu entfernen."
	fi
fi

exit 0
