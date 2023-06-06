-- ===================================================================
-- Copyright (C) 2014-2019		Charlene BENKE		<charlie@patas-monkey.com>
--
-- This program is free software; you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation; either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program. If not, see <http://www.gnu.org/licenses/>.
--
-- ===================================================================

create table llx_projet_stock
(
	rowid				integer		PRIMARY KEY NOT NULL AUTO_INCREMENT,
	fk_project			integer		NOT NULL DEFAULT 0,		-- projet 
	fk_product 			integer		NOT NULL DEFAULT 0,		-- produit mouvementé
	fk_entrepot 		integer		NOT NULL DEFAULT 0,		-- entrepot d'origine
	qty_from_stock		double 		DEFAULT NULL,		-- quantité produit récupéré du stock
	date_creation		datetime	DEFAULT NULL,		-- date de création du mouvement
	fk_user_author		integer,						-- createur de la fiche
	tms					timestamp,
	pmp					double(24,8) DEFAULT 0,
	price				double(24,8) DEFAULT 0,
	fk_product_stock	integer,						-- id du mvt de stock
	note_public			text
)ENGINE=innodb;

-- 
-- List of codes for special_code
--
-- 1 : frais de port
-- 2 : ecotaxe
-- 3 : produit/service propose en option
--
