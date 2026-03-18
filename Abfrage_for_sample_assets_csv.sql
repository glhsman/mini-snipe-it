SELECT s.inventarnr AS asset_tag,
       a.name_of AS name,
       s.sn AS serial,
       a.name_of AS model_name,
       h.herstellername AS manufacturer_name,
       k.devicename AS category_name,
       CASE 
           WHEN s.aktiv = 1 THEN 'Einsatzbereit'
           WHEN s.aktiv = 0 THEN 'Ausgemustert'
           ELSE 'Unbekannt'
       END AS status_name,
       g.name_of AS location_name,
       u.winname AS assigned_username,
       u.vname AS assigned_first_name,
       u.zname AS assigned_last_name
FROM sn s
INNER JOIN assets a ON a.assetid = s.assetref
INNER JOIN hersteller h ON h.herstellerid = a.herstellerref
INNER JOIN devicegroup k ON k.deviceid = a.groupref
INNER JOIN user u ON u.userid = s.userref
INNER JOIN gfgh g ON g.gfghid = s.gfghref
;