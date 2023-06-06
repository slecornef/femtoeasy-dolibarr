--- V4 Major for V15
ALTER TABLE  llx_factorydet			ADD qty_product_ok			INTEGER NOT NULL DEFAULT 0;		-- La quantité présente chez le sous-traitant
ALTER TABLE  llx_factory			ADD fk_soc					integer NULL DEFAULT NULL;
ALTER TABLE  llx_factory			ADD ref_supplier			varchar(64)	NULL DEFAULT NULL;
ALTER TABLE  llx_factory			ADD delivery_date			date default NULL;
ALTER TABLE  llx_factory			ADD fk_user_commande		integer;

--- V3 MAJOR FOR V12
ALTER TABLE  llx_factory			ADD  fk_projet             Integer 		NULL DEFAULT NULL;

ALTER TABLE  llx_factory			ADD  fk_bom             Integer 		NULL DEFAULT NULL;
ALTER TABLE  llx_factorydet			DROP INDEX  uk_factorydet ;
ALTER TABLE  llx_factorydet			ADD INDEX  uk_factorydet (fk_factory,fk_product, globalqty);


--- V2 major
ALTER TABLE  llx_factory			ADD  label				varchar(255)	NULL DEFAULT NULL;
ALTER TABLE  llx_factory			ADD  fk_factory_parent	Integer 		NULL DEFAULT NULL;
ALTER TABLE  llx_factory			ADD  datec				datetime		DEFAULT NULL;


ALTER TABLE  llx_factory			ADD  fk_user_modif		Integer 		NULL DEFAULT NULL;

ALTER TABLE  llx_product_factory	ADD  import_key			VARCHAR( 14 ) 	NULL DEFAULT NULL;
ALTER TABLE  llx_product_factory	ADD  extraparams		VARCHAR(255);

ALTER TABLE  llx_factory			ADD  import_key			VARCHAR( 14 ) 	NULL DEFAULT NULL;
ALTER TABLE  llx_factory			ADD  extraparams		VARCHAR(255);

ALTER TABLE  llx_equipement_factory	ADD  children 			INTEGER NOT 	NULL DEFAULT 0;		-- Nouveau champ pour l'ordre dans la liste des composants

--- Factory contact
insert into llx_c_type_contact(rowid, element, source, code, libelle, active ) values (251, 'factory', 'internal', 'FACTORYRESP', 'Responsable Production', 1);
insert into llx_c_type_contact(rowid, element, source, code, libelle, active ) values (252, 'factory', 'internal', 'INTERVENING', 'Intervenant', 1);

insert into llx_c_type_contact(rowid, element, source, code, libelle, active ) values (241, 'factory', 'external', 'FACTORYRESP', 'Responsable Production', 1);
insert into llx_c_type_contact(rowid, element, source, code, libelle, active ) values (242, 'factory', 'exterrnal', 'INTERVENING', 'Intervenant', 1);

ALTER TABLE  llx_factorydet			ADD  ordercomponent 	INTEGER NOT NULL DEFAULT 0;		-- Nouveau champ pour l'ordre dans la liste des composants
ALTER TABLE  llx_product_factory	ADD  ordercomponent 	INTEGER NOT NULL DEFAULT 0;
ALTER TABLE  llx_factorydet			ADD  globalqty 			INTEGER NOT NULL DEFAULT 0;		-- La quantité est à prendre au détail ou au global
ALTER TABLE  llx_factorydet			ADD  description		text	;
ALTER TABLE  llx_product_factory	ADD  globalqty 			INTEGER NOT NULL DEFAULT 0;		-- La quantit� est à prendre au d�tail ou au global
ALTER TABLE  llx_product_factory	ADD  description		text	;
ALTER TABLE  llx_factory			ADD  entity				Integer NOT NULL DEFAULT 1;