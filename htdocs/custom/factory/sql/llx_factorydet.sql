-- ===================================================================
-- Copyright (C) 2014-2022		Charlene BENKE		<charlene@patas-monkey.com>
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

CREATE TABLE llx_factorydet (
  rowid 				integer NOT NULL AUTO_INCREMENT,
  fk_factory 			integer NOT NULL DEFAULT 0,
  fk_product 			    integer NOT NULL DEFAULT 0,
  qty_unit 				    double DEFAULT NULL,	            -- quantité unitaire de produit dans la composition
  qty_planned 			  double DEFAULT NULL,	          -- quantité total prévu d'etre utilisé
  qty_used 				    double DEFAULT NULL,	            -- quantité finalement utilisé
  qty_deleted 			  double DEFAULT NULL,
  pmp 					      double(24,8) DEFAULT 0,
  price 				      double(24,8) DEFAULT 0,
  fk_mvtstockplanned	integer NOT NULL DEFAULT 0,   -- pour mémoriser le mouvement de stock prévisionnel (et ne plus le faire)
  fk_mvtstockused		  integer NOT NULL DEFAULT 0,   -- pour mémoriser le mouvement de stock réel (et ne plus le faire)
  fk_mvtstockother		integer NOT NULL DEFAULT 0,   -- pour mémoriser le mouvement de stock autre (detruit ou retour stock)
  note_public			    text,
  ordercomponent		  integer NOT NULL DEFAULT 0,		-- l'ordre d'affichage des composants
  globalqty 			    integer NOT NULL DEFAULT 0,		-- La quantité est à prendre au détail ou au global
  description			    text,         					      -- description
  qty_product_ok			INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (rowid),
  UNIQUE KEY uk_factorydet (fk_factory,fk_product, globalqty)
 
) ENGINE=InnoDB ;
