connect 1_3_rights3;
delete from nodes;
insert into nodes values (3, "Latvia", 2, "127.0.0.1", 15053, 30, 365, 1, 4);
insert into nodes values (2, "Riga",   2, "127.0.0.1", 15052, 30, 365, 0, 3);
insert into nodes values (1, "Dpils",  2, "127.0.0.1", 15051, 30, 365, 0, 3);
connect 1_3_rights2;
delete from nodes;
insert into nodes values (3, "Latvia", 2, "127.0.0.1", 15053, 30, 365, 0, 4);
insert into nodes values (2, "Riga",   2, "127.0.0.1", 15052, 30, 365, 1, 3);
insert into nodes values (1, "Dpils",  2, "127.0.0.1", 15051, 30, 365, 0, 3);
connect 1_3_rights1;
delete from nodes;
insert into nodes values (3, "Latvia", 2, "127.0.0.1", 15053, 30, 365, 0, 4);
insert into nodes values (2, "Riga",   2, "127.0.0.1", 15052, 30, 365, 0, 3);
insert into nodes values (1, "Dpils",  2, "127.0.0.1", 15051, 30, 365, 1, 3);
