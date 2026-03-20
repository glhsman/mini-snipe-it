SELECT g.name_of as name, CONCAT_WS(' ', strasse, hausnummer) AS address,
CONCAT_WS(' ', plz, ort) AS city, gfgh_kuerzel as kuerzel
FROM gfgh g;