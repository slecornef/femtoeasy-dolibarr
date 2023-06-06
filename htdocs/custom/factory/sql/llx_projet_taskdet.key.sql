-- ===================================================================
-- Copyright (C) 2013		Charles-Fr BENKE		<charles.fr@benke.fr>
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



ALTER TABLE llx_projet_taskdet ADD INDEX idx_projet_taskdet_fk_task (fk_task);
ALTER TABLE llx_projet_taskdet ADD INDEX idx_projet_taskdet_fk_product (fk_product);

ALTER TABLE llx_projet_taskdet ADD CONSTRAINT fk_projet_taskdet_fk_task FOREIGN KEY (fk_task) REFERENCES llx_projet_task (rowid);
