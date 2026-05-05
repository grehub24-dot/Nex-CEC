<?php
echo "<h1>Diagnostic</h1>";
echo "<p>__DIR__: " . __DIR__ . "</p>";
echo "<p>CWD: " . getcwd() . "</p>";
echo "<h3>Files in __DIR__:</h3><pre>";
print_r(scandir(__DIR__));
echo "</pre>";
echo "<h3>Parent dir contents:</h3><pre>";
print_r(scandir(__DIR__ . "/../"));
echo "</pre>";
echo "<h3>../includes/ contents:</h3><pre>";
if (is_dir(__DIR__ . "/../includes")) {
    print_r(scandir(__DIR__ . "/../includes"));
} else {
    echo "Directory does not exist!";
}
echo "</pre>";

