function copyCode(button) {
    if (!button) return;
    const pre = button.parentElement;
    if (!pre) return;
    const code = pre.querySelector('code');
    if (!code) return;
    const text = code.innerText;

    navigator.clipboard.writeText(text).then(() => {
        const originalText = button.innerText;
        button.innerText = 'Copied!';
        button.classList.add('copied');
        
        setTimeout(() => {
            button.innerText = originalText;
            button.classList.remove('copied');
        }, 2000);
    }).catch((err) => {
        console.error('Failed to copy code snippet: ', err);
    });
}
