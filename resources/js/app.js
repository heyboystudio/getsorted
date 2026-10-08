import './push';

// After an action the page moves to what just changed and highlights it, so nothing appears out of sight (spec 028).
document.addEventListener('livewire:init', () => {
    window.Livewire.on('focus-section', (event) => {
        const id = Array.isArray(event) ? event[0]?.id : event?.id;
        requestAnimationFrame(() => {
            const section = id ? document.getElementById(id) : null;
            if (!section) { return; }
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            section.classList.add('ring-2', 'ring-zinc-900/30', 'transition');
            setTimeout(() => section.classList.remove('ring-2', 'ring-zinc-900/30'), 2500);
        });
    });
});
