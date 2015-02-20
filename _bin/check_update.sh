#!/bin/bash
DIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
cd "$DIR/../"
git pull origin master

# run update shell scripts,
# only once by checking file.sh.done
for file in _bin/update_sh/*.sh
do
    # script not executed yet
    if [ -f $file ] && [ ! -f $file.done ]; then
        /bin/bash $file
        touch $file.done
    fi
done

# removed script => remove *.done
for file in _bin/update_sh/*.done
do
    filename="${file%.*}"
    # script no longer exists in repo
    if [ -f $filename.done ] && [ ! -f $filename ]; then
        rm $filename.done
    fi
done
