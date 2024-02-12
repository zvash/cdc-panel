setTimeout(function () {
    localStorage.setItem("novaTheme", "light");
}, 1000);
document.documentElement.classList.remove('dark');

setInterval(overrideTitle, 1000);

function overrideTitle() {
    document.title = 'SPACE';

}

overrideTitle();
