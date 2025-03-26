
// Generación de nombre de carpeta
const urlActual = window.location.href;
const parametros = new URLSearchParams(window.location.search);
let carpetaNombre = parametros.get("nombre");

if (!carpetaNombre) {
    carpetaNombre = generarCadenaAleatoria();
    const separador = urlActual.includes('?') ? '&' : '?';
    window.location.href = `${urlActual}${separador}nombre=${carpetaNombre}`;
}

// Función generadora de cadena
function generarCadenaAleatoria() {
    return Math.random().toString(36).substr(2, 3);
}

// Drag and Drop modificado para múltiples archivos
const dropArea = document.getElementById('drop-area');
const Form = document.getElementById('form');

dropArea.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropArea.classList.add('drag-over');
});

dropArea.addEventListener('dragleave', () => {
    dropArea.classList.remove('drag-over');
});

dropArea.addEventListener('drop', (e) => {
    e.preventDefault();
    dropArea.classList.remove('drag-over');
    handleFiles(e.dataTransfer.files);
});

function handleFiles(files) {
    const input = document.getElementById('archivo');
    const dataTransfer = new DataTransfer();
    
    // Agregar nuevos archivos al input
    Array.from(files).forEach(file => dataTransfer.items.add(file));
    input.files = dataTransfer.files;
    
    // Mostrar cantidad de archivos
    const counter = document.createElement('div');
    counter.className = 'file-counter';
    counter.textContent = `${files.length} archivo(s)`;
    dropArea.appendChild(counter);
    
    // Auto-submit después de 1 segundo
    setTimeout(() => Form.submit(), 1000);
}

// Validación de formulario
Form.addEventListener('submit', (e) => {
    const files = document.getElementById('archivo').files;
    if (files.length === 0) {
        e.preventDefault();
        alert('Selecciona al menos un archivo');
    }
});

// Eliminar contador al vaciar
document.getElementById('archivo').addEventListener('change', function() {
    const counter = document.querySelector('.file-counter');
    if (this.files.length === 0 && counter) {
        counter.remove();
    }
});
