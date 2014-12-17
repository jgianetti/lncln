#!/bin/sh

printf "Bakupeando DB\n"
mysqldump -u pnfp -p --databases pnfp | bzip2 -c > pnfp.$(date +%Y-%m-%d.%H%M)hs.sql.bz2

printf "Actualizando DB\n"
for i in pnfp.*.$(date +%Y-%m-%d.%H%M)hs.sql
do
    printf " -- $i\n"
    mysqldump -u pnfp -p pnfp < $i
done

printf "\nPulleando GIT\n"
cd ../
git pull origin
printf "\nDone\n"