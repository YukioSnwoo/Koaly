function getUsuario() {
    const data = sessionStorage.getItem('usuario');
    if (!data) return null;
    try {
        return JSON.parse(data);
    } catch {
        return null;
    }
}

function requireRol(rolEsperado) {
    const usuario = getUsuario();
    if (!usuario || Number(usuario.id_rol) !== Number(rolEsperado)) {
        window.location.href = '../index.html';
        return null;
    }

    if (Number(rolEsperado) !== 1 && !usuario.id_sucursal) {
        sessionStorage.removeItem('usuario');
        window.location.href = '../index.html';
        return null;
    }

    return usuario;
}

function logout() {
    sessionStorage.removeItem('usuario');
    window.location.href = '../index.html';
}
