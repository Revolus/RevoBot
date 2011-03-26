#!/bin/sh
php mysqlbestandschecker.php > results/result.csv

cd results
grep -e '^0,' result.csv > wrong.csv

grep -e ',0$' wrong.csv > commons.csv
grep -e ',2$' wrong.csv > missing.csv
grep -e ',4$' wrong.csv > duplicate.csv
grep -e ',6$' wrong.csv > shadows.csv
grep -e ',8$' wrong.csv > renamed.csv
grep -e ',10$' wrong.csv > local.csv

sed 's/[^,]*,\([^,]*\),[^,]*,.*/\1/' commons.csv   | paste -s -d',' > commons.ids
sed 's/[^,]*,\([^,]*\),[^,]*,.*/\1/' missing.csv   | paste -s -d',' > missing.ids
sed 's/[^,]*,\([^,]*\),[^,]*,.*/\1/' duplicate.csv | paste -s -d',' > duplicate.ids
sed 's/[^,]*,\([^,]*\),[^,]*,.*/\1/' shadows.csv   | paste -s -d',' > shadows.ids
sed 's/[^,]*,\([^,]*\),[^,]*,.*/\1/' renamed.csv   | paste -s -d',' > renamed.ids
sed 's/[^,]*,\([^,]*\),[^,]*,.*/\1/' local.csv     | paste -s -d',' > local.ids

echo commons:
wc -l commons.csv
echo missing:
wc -l missing.csv
echo duplicate:
wc -l duplicate.csv
echo shadows:
wc -l shadows.csv
echo renamed:
wc -l renamed.csv
echo local:
wc -l local.csv
echo wrong:
wc -l wrong.csv
