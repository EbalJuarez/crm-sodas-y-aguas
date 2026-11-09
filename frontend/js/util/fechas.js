/** Formatea una fecha UTC en hora fija de Guatemala, sin depender de la zona del navegador. */
export function formatearGuatemala(valor) {
    if (!valor) {
        return '—';
    }

    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) {
        return '—';
    }

    const guatemala = new Date(fecha.getTime() - (6 * 60 * 60 * 1000));
    const dos = (numero) => String(numero).padStart(2, '0');

    return `${dos(guatemala.getUTCDate())}/${dos(guatemala.getUTCMonth() + 1)}/${guatemala.getUTCFullYear()} ${dos(guatemala.getUTCHours())}:${dos(guatemala.getUTCMinutes())}`;
}

export function mesActualGuatemala() {
    const fecha = new Date(Date.now() - (6 * 60 * 60 * 1000));
    return `${fecha.getUTCFullYear()}-${String(fecha.getUTCMonth() + 1).padStart(2, '0')}`;
}

/** Convierte el valor de un input datetime-local, interpretado como hora de Guatemala. */
export function fechaLocalGuatemalaAISO(valor) {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(valor)) {
        return null;
    }

    return `${valor}:00-06:00`;
}
