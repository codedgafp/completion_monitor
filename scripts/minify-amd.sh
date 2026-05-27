#!/bin/sh
set -e

echo "Minification des fichiers AMD..."

find ./amd/src -type f -name "*.js" | while read src; do
    relative=$(echo "$src" | sed 's|./amd/src/||')
    dir=$(dirname "$relative")
    filename=$(basename "$relative" .js)

    dest_dir="./amd/build/${dir}"
    dest="${dest_dir}/${filename}.min.js"

    mkdir -p "$dest_dir"

    echo "[minify] $src -> $dest"

    npx --yes terser "$src" \
        --compress \
        --mangle \
        --source-map "filename='${filename}.min.js.map',url='${filename}.min.js.map',root=''" \
        --output "$dest"
done

echo "Build AMD terminé."
