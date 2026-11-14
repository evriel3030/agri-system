-- =====================================================================
-- Agriculture Information System - 02_seed.sql
-- Run this SECOND (after 01_schema.sql) in the Supabase SQL Editor.
-- Loads lookup values, sample records and two login accounts.
--
--   Login accounts (CHANGE THESE after your first login / before demo):
--     admin / Admin@123   (Administrator)
--     staff / Staff@123   (Staff)
-- =====================================================================

-- ---------- Lookup data ----------
INSERT INTO soil_type (soil_type_id, soil_type_name) VALUES
 (1,'Loam'),(2,'Clay Loam'),(3,'Sandy Loam'),(4,'Clay');

INSERT INTO irrigation_type (irrigation_type_id, irrigation_type_name) VALUES
 (1,'Rainfed'),(2,'Irrigated'),(3,'Drip Irrigation');

INSERT INTO crop_category (category_id, category_name) VALUES
 (1,'Cereal'),(2,'Vegetable'),(3,'Fruit'),(4,'Legume'),(5,'Root Crop');

INSERT INTO season (season_id, season_name) VALUES
 (1,'Wet Season'),(2,'Dry Season'),(3,'Year Round');

INSERT INTO planting_status (status_id, status_name) VALUES
 (1,'Planted'),(2,'Growing'),(3,'Harvested'),(4,'Failed');

INSERT INTO quality_grade (quality_grade_id, grade_name) VALUES
 (1,'Grade A'),(2,'Grade B'),(3,'Grade C'),(4,'Grade D');

-- Harvest remarks: exactly the values given in the project document
INSERT INTO harvest_remark (remark_id, remark_description) VALUES
 (1,'Good Harvest'),(2,'Acceptable Quality'),(3,'Low Yield'),
 (4,'Poor Quality'),(5,'Damaged Harvest');

INSERT INTO role (role_id, role_name) VALUES (1,'Administrator'),(2,'Staff');

-- ---------- Users (passwords are bcrypt hashes, not plain text) ----------
INSERT INTO app_user (user_id, username, password_hash, full_name, role_id) VALUES
 (1,'admin','$2y$10$Ewj1nWxKb7h4xV9ZIPFwheGnGtybZ2nVVJt3OWfWYLL16mHGa67iO','System Administrator',1),
 (2,'staff','$2y$10$VbLMXNusHe5emASM1MSJcOARlaBYiFMCiM5mIWRW2zVjhwYRm0rpa','Staff Member',2);

-- ---------- Crops (from the project document) ----------
INSERT INTO crop_type (crop_type_id, crop_name, category_id) VALUES
 (1,'Rice',1),(2,'Corn',1),(3,'Tomato',2),(4,'Eggplant',2),(5,'Banana',3),
 (6,'Mango',3),(7,'Peanut',4),(8,'Cassava',5),(9,'Sweet Potato',5),(10,'Watermelon',3);

INSERT INTO crop (crop_id, crop_type_id, variety, growing_period_days, season_id) VALUES
 (1,1,'NSIC Rc 222',115,1),
 (2,2,'Yellow Corn',100,2),
 (3,3,'Diamante Max',90,2),
 (4,4,'Morena',100,3),
 (5,5,'Lakatan',300,3),
 (6,6,'Carabao',150,3),
 (7,7,'BPI Pn 9',110,3),
 (8,8,'Golden Yellow',240,3),
 (9,9,'NSIC Sp 30',120,3),
 (10,10,'Sweet 16',85,2);

-- ---------- Farmers (from the project document) ----------
INSERT INTO farmer (farmer_id, first_name, last_name, contact_number, address, date_registered) VALUES
 (1,'Juan','Dela Cruz','09171234567','Cebu City','2026-10-26'),
 (2,'Maria','Santos','09181234567','Carcar City, Cebu','2026-10-26'),
 (3,'Pedro','Reyes','09191234567','Danao City, Cebu','2026-10-26'),
 (4,'Ana','Garcia','09201234567','Argao, Cebu','2026-10-26'),
 (5,'Jose','Mendoza','09211234567','Bogo City, Cebu','2026-10-26'),
 (6,'Ramon','Flores','09221234567','Toledo City, Cebu','2026-10-26'),
 (7,'Elena','Cruz','09231234567','Naga City, Cebu','2026-10-26'),
 (8,'Antonio','Lim','09241234567','Minglanilla, Cebu','2026-10-26'),
 (9,'Rosa','Fernandez','09251234567','Liloan, Cebu','2026-10-26'),
 (10,'Mario','Villanueva','09261234567','Compostela, Cebu','2026-10-26'),
 (11,'Teresa','Navarro','09271234567','Talisay City, Cebu','2026-10-26'),
 (12,'Carlos','Ramos','09281234567','Consolacion, Cebu','2026-10-26'),
 (13,'Liza','Torres','09291234567','Dalaguete, Cebu','2026-10-26'),
 (14,'Roberto','Castillo','09301234567','Barili, Cebu','2026-10-26'),
 (15,'Susan','Aquino','09311234567','Sibonga, Cebu','2026-10-26');

-- ---------- Farms (from the project document) ----------
-- soil_type_id: 1 Loam, 2 Clay Loam, 3 Sandy Loam, 4 Clay
-- irrigation_type_id: 1 Rainfed, 2 Irrigated, 3 Drip Irrigation
INSERT INTO farm (farm_id, farmer_id, farm_name, location, area_hectares, soil_type_id, irrigation_type_id) VALUES
 (1,1,'Dela Cruz Farm','Cebu City',2.50,1,1),
 (2,2,'Santos Rice Farm','Carcar City, Cebu',4.20,2,2),
 (3,3,'Reyes Agricultural Farm','Danao City, Cebu',3.80,3,1),
 (4,4,'Garcia Vegetable Farm','Argao, Cebu',1.75,1,3),
 (5,5,'Mendoza Farm','Bogo City, Cebu',5.00,4,2),
 (6,6,'Flores Corn Farm','Toledo City, Cebu',3.40,3,1),
 (7,7,'Cruz Family Farm','Naga City, Cebu',2.80,1,2),
 (8,8,'Lim Agricultural Farm','Minglanilla, Cebu',2.25,2,3),
 (9,9,'Fernandez Farm','Liloan, Cebu',3.10,3,1),
 (10,10,'Villanueva Green Farm','Compostela, Cebu',4.50,1,2),
 (11,11,'Navarro Rice Field','Talisay City, Cebu',3.75,4,2),
 (12,12,'Ramos Farm','Consolacion, Cebu',2.60,1,1),
 (13,13,'Torres Vegetable Farm','Dalaguete, Cebu',1.80,3,3),
 (14,14,'Castillo Agricultural Farm','Barili, Cebu',5.50,2,2),
 (15,15,'Aquino Farm','Sibonga, Cebu',3.20,1,1);

-- ---------- Planting records ----------
-- The document shows rows 11-28 only, so the same pattern is continued
-- to create 30 records. Each farm/crop pair follows the document's cycle.
-- Status: 1-20 Harvested, 21-28 Growing, 29 Planted, 30 Failed.
INSERT INTO planting (planting_id, farm_id, crop_id, planting_date, area_planted,
                      seed_quantity, expected_harvest_date, status_id)
SELECT g,
       ((g - 1) % 15) + 1,
       ((g - 1) % 10) + 1,
       d.pdate,
       0.5 + 0.1 * ((g - 1) % 10),
       10 + 5 * (((g - 9) % 8 + 8) % 8),
       d.pdate + c.growing_period_days,
       CASE WHEN g <= 20 THEN 3
            WHEN g <= 28 THEN 2
            WHEN g = 29  THEN 1
            ELSE 4 END
FROM generate_series(1, 30) AS g
CROSS JOIN LATERAL (SELECT DATE '2026-01-10' + 3 * (g - 1) AS pdate) AS d
JOIN crop c ON c.crop_id = ((g - 1) % 10) + 1;

-- ---------- Harvest records (one for each of plantings 1-20) ----------
-- Grade cycles A,B,C. Remarks: Grade A/B -> 1 Good Harvest,
-- Grade C -> 2 Acceptable Quality (the document's free-text remarks
-- "High quality harvest", "Good harvest", "Acceptable quality" are
-- converted to the numeric remark_id values).
INSERT INTO harvest (harvest_id, planting_id, harvest_date, quantity_kg,
                     selling_price_per_kg, quality_grade_id, remark_id)
SELECT g,
       g,
       p.expected_harvest_date,
       900 + 125 * (g - 1),
       22 + 3.5 * ((g - 1) % 8),
       ((g - 1) % 3) + 1,
       CASE WHEN (g - 1) % 3 = 2 THEN 2 ELSE 1 END
FROM generate_series(1, 20) AS g
JOIN planting p ON p.planting_id = g;

-- ---------- Re-sync identity counters after inserting explicit IDs ----------
SELECT setval(pg_get_serial_sequence('soil_type','soil_type_id'),               (SELECT MAX(soil_type_id) FROM soil_type));
SELECT setval(pg_get_serial_sequence('irrigation_type','irrigation_type_id'),   (SELECT MAX(irrigation_type_id) FROM irrigation_type));
SELECT setval(pg_get_serial_sequence('crop_category','category_id'),            (SELECT MAX(category_id) FROM crop_category));
SELECT setval(pg_get_serial_sequence('season','season_id'),                     (SELECT MAX(season_id) FROM season));
SELECT setval(pg_get_serial_sequence('planting_status','status_id'),            (SELECT MAX(status_id) FROM planting_status));
SELECT setval(pg_get_serial_sequence('quality_grade','quality_grade_id'),       (SELECT MAX(quality_grade_id) FROM quality_grade));
SELECT setval(pg_get_serial_sequence('harvest_remark','remark_id'),             (SELECT MAX(remark_id) FROM harvest_remark));
SELECT setval(pg_get_serial_sequence('role','role_id'),                         (SELECT MAX(role_id) FROM role));
SELECT setval(pg_get_serial_sequence('app_user','user_id'),                     (SELECT MAX(user_id) FROM app_user));
SELECT setval(pg_get_serial_sequence('farmer','farmer_id'),                     (SELECT MAX(farmer_id) FROM farmer));
SELECT setval(pg_get_serial_sequence('farm','farm_id'),                         (SELECT MAX(farm_id) FROM farm));
SELECT setval(pg_get_serial_sequence('crop_type','crop_type_id'),               (SELECT MAX(crop_type_id) FROM crop_type));
SELECT setval(pg_get_serial_sequence('crop','crop_id'),                         (SELECT MAX(crop_id) FROM crop));
SELECT setval(pg_get_serial_sequence('planting','planting_id'),                 (SELECT MAX(planting_id) FROM planting));
SELECT setval(pg_get_serial_sequence('harvest','harvest_id'),                   (SELECT MAX(harvest_id) FROM harvest));
