-- ===================================================================
-- Copyright (C) 2002-2003	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
-- Copyright (C) 2002-2003	Jean-Louis Bergamo		<jlb@j1b.org>
-- Copyright (C) 2009		    Regis Houssin			    <regis.houssin@capnetworks.com>
-- Copyright (C) 2014-2021	Charlene BENKE			  <charlene@patas-monkey.com> 
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
--
-- Structure de la table llx_product_factory
--
-- Contient la composition des produits en fabrication

CREATE TABLE IF NOT EXISTS llx_product_factory (
  rowid				      integer NOT NULL AUTO_INCREMENT,
  fk_product_father	integer NOT NULL DEFAULT 0,		-- clé produit composé
  fk_product_children	integer NOT NULL DEFAULT 0,		-- clé produit composant
  pmp					      double(24,8) DEFAULT 0,			-- prix unitaire d'achat
  price				      double(24,8) DEFAULT 0,			-- prix unitaire de vente
  qty 				      double DEFAULT NULL,			-- quantité entrant dans la fabrication
  ordercomponent		integer NOT NULL DEFAULT 0,			-- l'ordre d'affichage des composants
  globalqty			    integer NOT NULL DEFAULT 0,			-- La quantité est à prendre au détail ou au global
  description			  text,         					-- description
  PRIMARY KEY (rowid),
  UNIQUE KEY uk_product_factory (fk_product_father,fk_product_children),
  KEY idx_product_factory_fils (fk_product_children)
) ENGINE=InnoDB ;
