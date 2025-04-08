<?php
session_start();

// Habilitar reporte de errores (solo para desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$carpetaNombre = isset($_GET['nombre']) ? basename($_GET['nombre']) : '';
$carpetaRuta = "./descarga/" . $carpetaNombre;
$carpetaUrl = "descarga/" . $carpetaNombre;
$passwordFile = './passwords.json';

function eliminarDirectorio($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!eliminarDirectorio($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    
    return rmdir($dir);
}

$passwords = [];
if (file_exists($passwordFile)) {
    $passwords = json_decode(file_get_contents($passwordFile), true) ?: [];
}

try {
    // Paso 1: Formulario inicial para nombre de carpeta
    if ($carpetaNombre === '') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['nombre'])) {
            $carpetaNombre = basename($_GET['nombre']);
            header("Location: ?nombre=" . urlencode($carpetaNombre));
            exit;
        }
    }

    // Paso 2: Crear carpeta con contraseña
    if (!file_exists($carpetaRuta) && $carpetaNombre !== '') {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_password'])) {
            $passwordUsuario = trim($_POST['crear_password']);
            $confirmPassword = trim($_POST['confirm_password']);
            
            if ($passwordUsuario !== $confirmPassword) {
                throw new Exception("Las contraseñas no coinciden");
            }
            
            if (strlen($passwordUsuario) < 6) {
                throw new Exception("La contraseña debe tener al menos 6 caracteres");
            }
            
            mkdir($carpetaRuta, 0755, true);
            $passwords[$carpetaNombre] = $passwordUsuario;
            file_put_contents($passwordFile, json_encode($passwords));
            $_SESSION['password_verificada'] = $carpetaNombre;
            
            header("Location: ?nombre=" . urlencode($carpetaNombre));
            exit;
        }
    }

    // Verificación de contraseña
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

    // Subida de archivos
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo'])) {
        $errores = [];
        
        foreach ($_FILES['archivo']['name'] as $key => $name) {
            // Corregido: Mejor saneamiento de nombres de archivo
            $nombreArchivo = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($name));
            $tmpName = $_FILES['archivo']['tmp_name'][$key];
            
            if ($_FILES['archivo']['error'][$key] === UPLOAD_ERR_OK) {
                $destino = $carpetaRuta . '/' . $nombreArchivo;
                if (!move_uploaded_file($tmpName, $destino)) {
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

    // Eliminar archivos
    if (isset($_POST['eliminarArchivo'])) {
        $archivoAEliminar = basename($_POST['eliminarArchivo']);
        $archivoRutaAEliminar = $carpetaRuta . '/' . $archivoAEliminar;

        if (file_exists($archivoRutaAEliminar)) {
            if (!eliminarDirectorio($archivoRutaAEliminar)) {
                throw new Exception("Error al eliminar el elemento.");
            }
        }
    }

    // Descarga ZIP
    if (isset($_GET['action']) && $_GET['action'] === 'download') {
        if (!isset($_SESSION['password_verificada']) || $_SESSION['password_verificada'] !== $carpetaNombre) {
            die('Acceso no autorizado');
        }
        
        $files = scandir($carpetaRuta);
        $files = array_diff($files, array('.', '..'));
        
        if (count($files) === 0) {
            die('La carpeta está vacía');
        }
        
        $zip = new ZipArchive();
        $zipFileName = tempnam(sys_get_temp_dir(), 'zip') . '.zip';
        
        if ($zip->open($zipFileName, ZipArchive::CREATE) !== TRUE) {
            die("No se pudo crear el archivo ZIP");
        }
        
        foreach ($files as $file) {
            $filePath = $carpetaRuta . '/' . $file;
            if (is_file($filePath)) {
                // Corregido: Usar solo el nombre del archivo sin rutas completas
                $zip->addFile($filePath, basename($filePath));
            }
        }
        
        $zip->close();
        
        // Headers mejorados para la descarga ZIP
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $carpetaNombre . '.zip"');
        header('Content-Length: ' . filesize($zipFileName));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($zipFileName);
        unlink($zipFileName);
        exit;
    }
        
} catch (Exception $e) {
    $mensaje = "Error: " . htmlspecialchars($e->getMessage());
}

// Función para obtener iconos (sin cambios)
function obtener_icono($file, $carpetaUrl) {
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $rutaCompleta = $carpetaUrl . '/' . $file;
    
    $extensionesImagen = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($extension, $extensionesImagen)) {
        return "<span onclick='copiarEnlaceArchivo(\"$rutaCompleta\")' style='cursor: pointer;'>
                <img src='$rutaCompleta' class='thumbnail' loading='lazy'>
                </span>";
    }
    
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
    return "<span onclick='copiarEnlaceArchivo(\"$rutaCompleta\")' style='cursor: pointer;'>
            <i class='fas $clase'></i>
            </span>";
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
    <?php if ($carpetaNombre === ''): ?>
    <!-- Formulario inicial para nombre de carpeta -->
    <div class="password-modal">
        <div class="password-content">
            <h3>📂 Crear Nueva Carpeta</h3>
            <form method="GET">
                <input type="text" 
                       name="nombre" 
                       placeholder="Nombre de la carpeta"
                       required
                       pattern="[a-zA-Z0-9_-]+"
                       title="Solo letras, números, guiones y guiones bajos">
                <button type="submit">Continuar</button>
            </form>
            <p style="margin-top:15px;font-size:0.9em;color:#666;">
                Ejemplo: <code>proyecto_final</code> o <code>documentos-importantes</code>
            </p>
        </div>
    </div>
    
    <?php elseif ($carpetaNombre !== '' && !file_exists($carpetaRuta)): ?>
    <!-- Formulario para crear contraseña -->
    <div class="password-modal">
        <div class="password-content">
            <h3>🔒 Proteger Carpeta: <em><?= $carpetaNombre ?></em></h3>
            <form method="POST">
                <input type="password" 
                       name="crear_password" 
                       placeholder="Nueva contraseña"
                       minlength="6"
                       required>
                
                <input type="password" 
                       name="confirm_password" 
                       placeholder="Confirmar contraseña"
                       minlength="6"
                       required>
                
                <button type="submit">Crear Carpeta Segura</button>
            </form>
            
            <?php if (isset($mensaje)): ?>
            <p class="error">⚠️ <?php echo $mensaje; ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <h1>Compartir archivos <sup class="beta">BETA</sup></h1>
    <div class="content">
        <h3>Sube tus archivos y comparte este enlace temporal: 
            <span id="share-link">ibu.pe/?nombre=<?php echo $carpetaNombre; ?></span>
            <i class="fa-solid fa-copy" onclick="copiarEnlace()" style="cursor: pointer; margin-left: 5px;"></i>
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
                    if (file_exists($targetDir)) {
                        $files = scandir($targetDir);
                        $files = array_diff($files, array('.', '..'));
                    } else {
                        $files = [];
                    }

                    if (count($files) > 0) {
                        echo "<h3 style='margin-bottom:10px;'>Archivos Subidos:</h3>";
                        foreach ($files as $file) {
                            $icono = obtener_icono($file, $carpetaUrl);
                            // Corregido: URL encode para nombres de archivo
                            echo "<div class='archivos_subidos'>
                                    <div class='file-icon'>$icono</div>
                                    <div class='file-info'>
                                        <a href='" . $carpetaUrl . '/' . urlencode($file) . "' download class='boton-descargar'>" . htmlspecialchars($file) . "</a>
                                    </div>
                                    <div>
                                        <form action='' method='POST' style='display:inline;'>
                                            <input type='hidden' name='eliminarArchivo' value='" . htmlspecialchars($file) . "'>
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
        
        <?php if (count($files) > 0): ?>
        <div class="download-all">
            <button onclick="descargarZIP()" class="btn-download-all">
                <i class="fas fa-file-archive"></i> Descargar todo como ZIP
            </button>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function copiarEnlace() {
        const link = document.getElementById('share-link').innerText;
        const password = "<?php echo isset($passwords[$carpetaNombre]) ? $passwords[$carpetaNombre] : ''; ?>";
        
        if (!password) {
            alert('Error: Debes establecer una contraseña primero');
            return;
        }
        
        const text = `Enlace: ${link}\nContraseña para acceder: ${password}`;
        
        navigator.clipboard.writeText(text).then(() => {
            alert('Enlace y contraseña copiados!\n\n' + text);
        }).catch(err => {
            alert('Error al copiar: ' + err);
        });
    }

    function copiarEnlaceArchivo(url) {
        navigator.clipboard.writeText(url).then(() => {
            alert('Enlace copiado:\n' + url);
        }).catch(err => {
            alert('Error al copiar: ' + err);
        });
    }

    function descargarZIP() {
        const url = `?nombre=<?= $carpetaNombre ?>&action=download`;
        
        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error('Error en la descarga: ' + response.statusText);
                return response.blob();
            })
            .then(blob => {
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `<?= $carpetaNombre ?>.zip`;
                document.body.appendChild(link);
                link.click();
                setTimeout(() => document.body.removeChild(link), 100);
            })
            .catch(err => {
                alert(err.message);
                console.error('Error:', err);
            });
    }

    document.querySelector('form').addEventListener('submit', function(e) {
        const pass1 = document.querySelector('[name="crear_password"]')?.value;
        const pass2 = document.querySelector('[name="confirm_password"]')?.value;
        
        if (pass1 && pass2) {
            if (pass1 !== pass2) {
                e.preventDefault();
                alert('🚨 Las contraseñas no coinciden');
            }
            
            if (pass1.length < 6) {
                e.preventDefault();
                alert('🔒 La contraseña debe tener al menos 6 caracteres');
            }
        }
    });
    </script>
</body>
</html>