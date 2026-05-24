function parseSize(sizeStr) {
    const units = { K: 1024, M: 1024 ** 2, G: 1024 ** 3 };
    const unit = sizeStr.slice(-1).toUpperCase();
    const value = parseFloat(sizeStr);
    return units[unit] ? value * units[unit] : value;
}

const uploadMaxFilesizeBytes = parseSize(uploadMaxFilesize);
const postMaxSizeBytes = parseSize(postMaxSize);

function validateFile(file) {
    if (!file) return;

    if (file.size > uploadMaxFilesizeBytes) {
        alert(`Soubor je moc velký, maximální velikost: ${uploadMaxFilesize}B`);
        document.getElementById('fileInput').value = '';
        return;
    }

    if (file.size > postMaxSizeBytes) {
        alert(`Soubor je moc velký, maximální velikost: ${postMaxSize}B`);
        document.getElementById('fileInput').value = '';
        return;
    }
}