# Verificación

## Matriz de criterios y evidencia (P3)

| Criterio | Evidencia | Resultado |
|----------|-----------|-----------|

## Pruebas de seguridad (S1–S8), incluidas las de peticiones simultáneas

## Cifras de control obtenidas

## Bitácora de IA (P5)
<!-- Al menos tres casos: qué propuso el agente, cómo se detectó, commit que lo corrigió. -->

### Caso 1 — Archivos restaurados sin que se pidiera

- **Fecha:** 2026-11-08 · **Integrante:** A
- **Qué falló:** antes del commit base, el agente restauró `config/app.php`, `config/database.php`,
  `.env.example` y `DatabaseSeeder.php` a la versión original de Laravel sin que se le pidiera. El
  commit `553dbbc`, que dice «MySQL y zona horaria UTC», no contenía esos cambios.
- **Cómo se detectó:** el agente comparó el contenido de `553dbbc` con lo esperado y lo reportó.
- **Corrección:** commit `ba05381` («laravel: configura MySQL y zona horaria UTC»).
- **Lección:** se agregó a `CLAUDE.md` la regla: «No restaures, reviertas ni sobrescribas archivos
  que no hayas creado en la tarea actual sin preguntarme antes. Si quieres dejar el árbol limpio para
  un commit, muéstrame `git diff` y espera mi OK. Antes de cada commit, compara su contenido con lo que
  promete el mensaje.»

### Casos 2 y 3

_Pendiente._
