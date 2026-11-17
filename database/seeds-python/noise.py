"""Ensucia datos limpios de forma determinista, para practicar el ETL de limpieza.

Ejemplos de ruido a implementar: espacios sobrantes, mayúsculas inconsistentes,
fechas en formatos mixtos, nulos disfrazados ("N/A", ""), duplicados casi idénticos.
"""
import random


def add_noise(rows: list[dict], rng: random.Random, rate: float = 0.05) -> list[dict]:
    """Devuelve una copia de `rows` con ruido aplicado a ~`rate` de los valores de texto."""
    noisy = []
    for row in rows:
        new = dict(row)
        for key, value in row.items():
            if isinstance(value, str) and rng.random() < rate:
                new[key] = rng.choice([value.upper(), f"  {value} ", value.lower(), "N/A"])
        noisy.append(new)
    return noisy
