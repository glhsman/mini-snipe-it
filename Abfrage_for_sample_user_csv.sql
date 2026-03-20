 -- User export---
 SELECT u.winname as username, u.email, u.vname as first_name, u.zname as last_name, g.name_of as location_name, u.personalnr as personalnummer,u.vorgesetzter, u.aktiv as status
 FROM user u
 INNER JOIN gfgh g ON g.gfghid=u.gfgh_id;
