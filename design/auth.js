document.addEventListener('DOMContentLoaded', function() {
    try {
        document.querySelectorAll('.login-btn').forEach(btn => {
            btn.onclick = () => {
                openAuthModal();
                showLogin();
            }
        });

        document.querySelectorAll('.register-btn').forEach(btn => {
            btn.onclick = () => {
                openAuthModal();
                showRegister();
            }
        });
    } catch (error) {
        console.error('Error setting up auth buttons:', error);
    }
});

function openAuthModal() {
    try {
        const modal = document.getElementById('authModal');
        if (!modal) throw new Error('Auth modal not found');
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.style.overflow = 'hidden';
    } catch (error) {
        console.error('Error opening auth modal:', error);
    }
}