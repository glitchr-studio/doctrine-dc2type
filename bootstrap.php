<?php

function file_replace(array|string $search, array|string $replace, array|string $fname, int &$count = null): void
{
    if (!is_array($fname)) {
        $fname = [$fname];
    }
    
    foreach ($fname as $f) {
        if (!file_exists($f)) {
            echo "File '$f' does not exist.\n";
            continue;
        }

        $fileContents = file_get_contents($f);
        if ($fileContents === false) {
            echo "Could not read the file '$f'.\n";
            continue;
        }

        $newContents = str_replace($search, $replace, $fileContents, $replaceCount);
        if (file_put_contents($f, $newContents, LOCK_EX) === false) {
            echo "Could not write to the file '$f'.\n";
        } else {
            $count += $replaceCount;
        }
    }
}

function file_remove_line(string $search, array|string $fname): void
{
    if (!is_array($fname)) {
        $fname = [$fname];
    }

    foreach ($fname as $f) {
        if (!file_exists($f)) {
            echo "File '$f' does not exist.\n";
            continue;
        }

        $fileContents = file($f, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($fileContents === false) {
            echo "Could not read the file '$f'.\n";
            continue;
        }

        $newContents = array_filter($fileContents, function ($line) use ($search) {
            return strpos($line, $search) === false;
        });

        if (file_put_contents($f, implode(PHP_EOL, $newContents) . PHP_EOL, LOCK_EX) === false) {
            echo "Could not write to the file '$f'.\n";
        }
    }
}
