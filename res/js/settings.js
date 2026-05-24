function ThemeChange() {
    var modeSelect = document.getElementById("mode");
    var colorSelect = document.getElementById("color");
    var colorSelectLabel = document.querySelector("label[for='color']");

    if (modeSelect.value === "wallpaper") {
        colorSelect.style.display = "none";
        colorSelectLabel.style.display = "none";
    } else {
        colorSelect.style.display = "flex";
        colorSelectLabel.style.display = "flex";
    }
}

function handleThemeChange(colors) {
    var col = colors.replace(/"/g, '').split(', ');
    console.log(col);
    const root = document.querySelector(':root');
    var selectedMode = document.getElementById('mode').value;
    var selectedColor = document.getElementById('color').value;

    let blur = '0px';
    let background = '';

    if (selectedMode === 'dark' || selectedMode === 'light') {
        background = 'var(--secondary)';
    } else if (selectedMode === 'amoled') {
        background = 'rgb(19, 19, 26)';
    }

    if (selectedMode !== 'wallpaper') {
        blur = '0px';
        root.style.setProperty('--blurness-low', blur);
        root.style.setProperty('--blurness-high', blur);
    } else {
        selectedColor = 'l';
        selectedMode = 'light';
        root.style.setProperty('--blurness-low', blur);
        root.style.setProperty('--blurness-high', blur);
        background = 'linear-gradient(120deg, #fdd6d6 0%, #becdff 100%) center fixed no-repeat';
    }

    ThemeChange();
    document.documentElement.style.background = background;

    document.documentElement.classList.remove('dark', 'light', 'amoled', 'wallpaper');
    col.forEach(function(className) {
        document.documentElement.classList.remove(className);
    });
    document.documentElement.classList.add(selectedMode, selectedColor);
}