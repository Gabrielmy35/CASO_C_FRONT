<?php
$files = scandir($carpetaRuta);
$files = array_diff($files, ['.', '..']);

if (count($files) > 0) {
    echo "<h3 style='margin-bottom:10px;'>Archivos Subidos:</h3>";
    foreach ($files as $file) {
        echo "<div class='archivos_subidos'>
                <!-- Mismo formato que en el JavaScript -->
              </div>";
    }
} else {
    echo "No se han subido archivos.";
}
?>