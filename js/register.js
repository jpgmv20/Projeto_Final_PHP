function chooseFile() {
    document.getElementById('avatar_input').click();
}

function enterUrl() {
    const urlInput = document.getElementById('avatar_url');
    urlInput.hidden = false;
    urlInput.focus();

    urlInput.addEventListener("change", () => {
        document.getElementById('avatar_preview').src = urlInput.value;
    });
}

function openAvatarOptions() {
    chooseFile();
}

document.getElementById("avatar_input").addEventListener("change", function() {
    const file = this.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById("avatar_preview").src = e.target.result;
    };
    reader.readAsDataURL(file);
});