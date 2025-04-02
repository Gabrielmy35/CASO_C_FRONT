<?php
session_start();

$carpetaNombre = isset($_GET['nombre']) ? basename($_GET['nombre']) : '';
$carpetaRuta = "./descarga/" . $carpetaNombre;
$passwordFile = './passwords.json';

// Función para generar contraseña aleatoria
function generarPassword($longitud = 8) {
    $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle($caracteres), 0, $longitud);
}

// Función para eliminar directorios recursivamente
function eliminarDirectorio($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!eliminarDirectorio($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    
    return rmdir($dir);
}

// Cargar contraseñas existentes
$passwords = [];
if (file_exists($passwordFile)) {
    $passwords = json_decode(file_get_contents($passwordFile), true) ?: [];
}

try {
    // Crear carpeta si no existe
    if (!file_exists($carpetaRuta) && $carpetaNombre !== '') {
        mkdir($carpetaRuta, 0755, true);
        // Generar y guardar contraseña
        $passwords[$carpetaNombre] = generarPassword();
        file_put_contents($passwordFile, json_encode($passwords));
        // Mostrar contraseña al crear
        $_SESSION['nueva_password'] = $passwords[$carpetaNombre];
    }

    // Verificar contraseña si existe
    if (isset($passwords[$carpetaNombre])) {
        if (!isset($_SESSION['password_verificada']) || $_SESSION['password_verificada'] !== $carpetaNombre) {
            if (isset($_POST['password'])) {
                if ($_POST['password'] === $passwords[$carpetaNombre]) {
                    $_SESSION['password_verificada'] = $carpetaNombre;
                } else {
                    throw new Exception("Contraseña incorrecta");
                }
            } else {
                throw new Exception("Se requiere contraseña");
            }
        }
    }

    // Manejar subida de archivos
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
        $errores = [];
        
        foreach ($_FILES['archivo']['name'] as $key => $name) {
            $nombreArchivo = str_replace(' ', '_', basename($name));
            $tmpName = $_FILES['archivo']['tmp_name'][$key];
            
            if ($_FILES['archivo']['error'][$key] === UPLOAD_ERR_OK) {
                if (!move_uploaded_file($tmpName, $carpetaRuta . '/' . $nombreArchivo)) {
                    $errores[] = "Error al subir: $name";
                }
            } else {
                $errores[] = "Error en: $name";
            }
        }
        
        if (!empty($errores)) {
            $mensaje = "Errores: " . implode(", ", $errores);
        } else {
            $mensaje = "Archivo(s) subidos correctamente";
        }
    }

    // Manejar eliminación de archivos
    if (isset($_POST['eliminarArchivo'])) {
        $archivoAEliminar = basename($_POST['eliminarArchivo']);
        $archivoRutaAEliminar = $carpetaRuta . '/' . $archivoAEliminar;

        if (file_exists($archivoRutaAEliminar)) {
            if (!eliminarDirectorio($archivoRutaAEliminar)) {
                throw new Exception("Error al eliminar el elemento.");
            }
        }
    }
        
} catch (Exception $e) {
    $mensaje = "Error: " . htmlspecialchars($e->getMessage());
}

function obtener_icono($file, $carpeta) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $rutaCompleta = "$carpeta/$file";
    
    // Miniaturas para imágenes
    $extensionesImagen = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($extension, $extensionesImagen)) {
        return "<img src='$rutaCompleta' class='thumbnail' loading='lazy'>";
    }
    
    // Iconos para otros tipos
    $iconos = [
        'pdf' => 'fa-file-pdf',
        'doc' => 'fa-file-word',
        'docx' => 'fa-file-word',
        'xls' => 'fa-file-excel',
        'xlsx' => 'fa-file-excel',
        'ppt' => 'fa-file-powerpoint',
        'pptx' => 'fa-file-powerpoint',
        'zip' => 'fa-file-archive',
        'rar' => 'fa-file-archive',
        'txt' => 'fa-file-alt',
        'mp3' => 'fa-file-audio',
        'mp4' => 'fa-file-video'
    ];
    
    $clase = $iconos[$extension] ?? 'fa-file';
    return "<i class='fas $clase'></i>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compartir archivos seguro</title>
    <link rel="stylesheet" href="estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php if (isset($passwords[$carpetaNombre]) && (!isset($_SESSION['password_verificada']) || $_SESSION['password_verificada'] !== $carpetaNombre)): ?>
        <div class="password-modal">
            <div class="password-content">
                <h3>Esta carpeta está protegida</h3>
                <form method="POST">
                    <input type="password" name="password" placeholder="Ingresa la contraseña" required>
                    <button type="submit">Acceder</button>
                </form>
                <?php if (isset($mensaje)): ?>
                    <p class="error"><?php echo $mensaje; ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <h1>Compartir archivos <sup class="beta">BETA</sup></h1>
    <div class="content">
    <h3>Sube tus archivos y comparte este enlace temporal: 
    <span id="share-link">ibu.pe/?nombre=<?php echo $carpetaNombre; ?></span>
    <i class="fa-solid fa-copy" onclick="copiarEnlace()" style="cursor: pointer; margin-left: 5px;"></i>
    <?php if (isset($_SESSION['nueva_password'])): ?>
        <div class="password-notice">
            <p>Contraseña para acceder: <strong><?php echo $_SESSION['nueva_password']; ?></strong></p>
            <button onclick="copiarEnlace()">Copiar enlace y contraseña</button>
        </div>
        <?php unset($_SESSION['nueva_password']); ?>
    <?php endif; ?>
</h3>
        <div class="container">
            <div class="drop-area" id="drop-area">
                <form action="" id="form" method="POST" enctype="multipart/form-data">
                    <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" style="fill:#0730c5;">
                        <path d="M13 19v-4h3l-4-5-4 5h3v4z"></path>
                        <path d="M7 19h2v-2H7c-1.654 0-3-1.346-3-3 0-1.404 1.199-2.756 2.673-3.015l.581-.102.192-.558C8.149 8.274 9.895 7 12 7c2.757 0 5 2.243 5 5v1h1c1.103 0 2 .897 2 2s-.897 2-2 2h-3v2h3c2.206 0 4-1.794 4-4a4.01 4.01 0 0 0-3.056-3.888C18.507 7.67 15.56 5 12 5 9.244 5 6.85 6.611 5.757 9.15 3.609 9.792 2 11.82 2 14c0 2.757 2.243 5 5 5z"></path>
                    </svg>
                    <input type="file" class="file-input" name="archivo[]" id="archivo" multiple onchange="document.getElementById('form').submit()">
                    <label> Arrastra tus archivos aquí<br>o</label>
                    <p><b>Abre el explorador</b></p>
                </form>
            </div>

            <div class="container2">
                <div id="file-list" class="pila">
                    <?php
                    if (isset($passwords[$carpetaNombre]) && (!isset($_SESSION['password_verificada']) || $_SESSION['password_verificada'] !== $carpetaNombre)) {
                        exit;
                    }
                    
                    $targetDir = $carpetaRuta;
                    $files = scandir($targetDir);
                    $files = array_diff($files, array('.', '..'));

                    if (count($files) > 0) {
                        echo "<h3 style='margin-bottom:10px;'>Archivos Subidos:</h3>";
                        foreach ($files as $file) {
                            $icono = obtener_icono($file, $carpetaRuta);
                            echo "<div class='archivos_subidos'>
                                    <div class='file-icon'>$icono</div>
                                    <div class='file-info'>
                                        <a href='$carpetaRuta/$file' download class='boton-descargar'>$file</a>
                                    </div>
                                    <div>
                                        <form action='' method='POST' style='display:inline;'>
                                            <input type='hidden' name='eliminarArchivo' value='$file'>
                                            <button type='submit' class='btn_delete'>
                                                <svg xmlns='http://www.w3.org/2000/svg' class='icon icon-tabler icon-tabler-trash' width='24' height='24' viewBox='0 0 24 24' stroke-width='2' stroke='currentColor' fill='none' stroke-linecap='round' stroke-linejoin='round'>
                                                    <path stroke='none' d='M0 0h24v24H0z' fill='none'/>
                                                    <path d='M4 7l16 0' />
                                                    <path d='M10 11l0 6' />
                                                    <path d='M14 11l0 6' />
                                                    <path d='M5 7l1 12a2 2 0 0 0 2 2h8a2 2 0 0 0 2 -2l1 -12' />
                                                    <path d='M9 7v-3a1 1 0 0 1 1 -1h4a1 1 0 0 1 1 1v3' />
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>";
                        }
                    } else {
                        echo "No se han subido archivos.";
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    function copiarEnlace() {
    const link = document.getElementById('share-link').innerText;
    const password = "<?php echo isset($passwords[$carpetaNombre]) ? $passwords[$carpetaNombre] : ''; ?>";
    
    if (!password) {
        alert('Esta carpeta no tiene contraseña configurada');
        return;
    }
    
    const text = `Enlace: ${link}\nContraseña para acceder: ${password}`;
    
    navigator.clipboard.writeText(text).then(() => {
        alert('Enlace y contraseña copiados!\n\n' + text);
    }).catch(err => {
        alert('Error al copiar: ' + err);
    });
}
    </script>
</body>
</html>