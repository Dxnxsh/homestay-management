-- =====================
-- STAFF
-- =====================
CREATE TABLE STAFF (
    staffID        INT PRIMARY KEY,
    staff_name      VARCHAR(100),
    staff_phoneNo   VARCHAR(20),
    staff_email     VARCHAR(100),
    staff_password  VARCHAR(100),
    staff_type      VARCHAR(20),
    managerID       INT,
    FOREIGN KEY (managerID) REFERENCES STAFF(staffID)
);

-- =====================
-- FULL_TIME (subtype)
-- =====================
CREATE TABLE FULL_TIME (
    staffID            INT PRIMARY KEY,
    full_time_salary   DECIMAL(10,2),
    vacation_days      INT,
    bonus              DECIMAL(10,2),
    FOREIGN KEY (staffID) REFERENCES STAFF(staffID)
);

-- =====================
-- PART_TIME (subtype)
-- =====================
CREATE TABLE PART_TIME (
    staffID      INT PRIMARY KEY,
    hourly_rate  DECIMAL(10,2),
    shift_time   VARCHAR(50),
    FOREIGN KEY (staffID) REFERENCES STAFF(staffID)
);

-- =====================
-- HOMESTAY
-- =====================
CREATE TABLE HOMESTAY (
    homestayID        INT PRIMARY KEY,
    homestay_name     VARCHAR(100),
    homestay_address  VARCHAR(200),
    office_phoneNo    VARCHAR(20),
    rent_price        DECIMAL(10,2),
    staffID           INT,
    FOREIGN KEY (staffID) REFERENCES STAFF(staffID)
);

-- =====================
-- GUEST
-- =====================
CREATE TABLE GUEST (
    guestID        INT PRIMARY KEY,
    guest_name     VARCHAR(100),
    guest_password VARCHAR(100),
    guest_phoneNo  VARCHAR(20),
    guest_gender   VARCHAR(10),
    guest_email    VARCHAR(100),
    guest_address  VARCHAR(200),
    guest_type     VARCHAR(20)
);

-- =====================
-- MEMBERSHIP
-- =====================
CREATE TABLE MEMBERSHIP (
    membershipID  INT PRIMARY KEY,
    guestID       INT UNIQUE,
    disc_rate     DECIMAL(5,2),
    FOREIGN KEY (guestID) REFERENCES GUEST(guestID)
);

-- =====================
-- BILL
-- =====================
CREATE TABLE BILL (
    billNo          INT PRIMARY KEY,
    bill_date       DATE,
    bill_subtotal   DECIMAL(10,2),
    disc_amount     DECIMAL(10,2),
    tax_amount      DECIMAL(10,2),
    total_amount    DECIMAL(10,2),
    late_charges    DECIMAL(10,2),
    bill_status     VARCHAR(20),
    payment_date    DATE,
    payment_method  VARCHAR(50),
    guestID         INT,
    staffID         INT,
    FOREIGN KEY (guestID) REFERENCES GUEST(guestID),
    FOREIGN KEY (staffID) REFERENCES STAFF(staffID)
);

-- =====================
-- BOOKING
-- =====================
CREATE TABLE BOOKING (
    bookingID      INT PRIMARY KEY,
    checkin_date   DATE,
    checkout_date  DATE,
    num_adults     INT,
    num_children   INT,
    deposit_amount DECIMAL(10,2),
    homestayID     INT,
    guestID        INT,
    staffID        INT,
    billNo         INT,
    FOREIGN KEY (homestayID) REFERENCES HOMESTAY(homestayID),
    FOREIGN KEY (guestID) REFERENCES GUEST(guestID),
    FOREIGN KEY (staffID) REFERENCES STAFF(staffID),
    FOREIGN KEY (billNo) REFERENCES BILL(billNo)
);

-- =====================
-- SERVICE
-- =====================
CREATE TABLE SERVICE (
    serviceID      INT PRIMARY KEY,
    service_type   VARCHAR(50),
    service_cost   DECIMAL(10,2),
    service_remark VARCHAR(200),
    staffID        INT,
    FOREIGN KEY (staffID) REFERENCES STAFF(staffID)
);

-- =====================
-- HOMESTAY_SERVICE (M:N)
-- =====================
CREATE TABLE HOMESTAY_SERVICE (
    homestayID  INT,
    serviceID   INT,
    main_date   DATE,
    main_status VARCHAR(20),
    PRIMARY KEY (homestayID, serviceID),
    FOREIGN KEY (homestayID) REFERENCES HOMESTAY(homestayID),
    FOREIGN KEY (serviceID) REFERENCES SERVICE(serviceID)
);

-- =====================
-- INSERT HOMESTAY DATA
-- =====================
-- Note: staffID is set to NULL. Update with actual staffID after inserting STAFF data.
INSERT INTO HOMESTAY (homestayID, homestay_name, homestay_address, office_phoneNo, rent_price, staffID) 
VALUES (1, 'THE GRAND HAVEN', 'HULU LANGAT, SELANGOR', '+60 17-204 2390', 500.00, NULL);

INSERT INTO HOMESTAY (homestayID, homestay_name, homestay_address, office_phoneNo, rent_price, staffID) 
VALUES (2, 'TWIN HAVEN', 'HULU LANGAT, SELANGOR', '+60 17-204 2390', 450.00, NULL);

INSERT INTO HOMESTAY (homestayID, homestay_name, homestay_address, office_phoneNo, rent_price, staffID) 
VALUES (3, 'THE RIVERSIDE RETREAT', 'GOPENG, PERAK', '+60 17-204 2390', 400.00, NULL);

INSERT INTO HOMESTAY (homestayID, homestay_name, homestay_address, office_phoneNo, rent_price, staffID) 
VALUES (4, 'HILLTOP HAVEN', 'GOPENG, PERAK', '+60 17-204 2390', 550.00, NULL);

-- =====================
-- STAFF DATA
-- =====================
-- Manager (supervises all staff)
INSERT INTO STAFF (staffID, staff_name, staff_phoneNo, staff_email, staff_password, staff_type, managerID)
VALUES (30410100909, 'MEOR DANISH BIN FARHAN', '0123456789', 'meor@gmail.com', 'meor123', 'MANAGER', NULL);

-- Full-time staff (supervised by manager)
INSERT INTO STAFF (staffID, staff_name, staff_phoneNo, staff_email, staff_password, staff_type, managerID)
VALUES (30107100907, 'MUHAMMAD SHAFIQ BIN DANIAL', '0134567890', 'shafiq@gmail.com', 'shafiq123', 'FULL TIME', 30410100909);

-- Part-time staff (supervised by manager)
INSERT INTO STAFF (staffID, staff_name, staff_phoneNo, staff_email, staff_password, staff_type, managerID)
VALUES (31214100905, 'MUHAMMAD ACAP BIN DANIAL', '0145678901', 'acap@gmail.com', 'acap123', 'PART TIME', 30410100909);

-- Full-time staff details
INSERT INTO FULL_TIME (staffID, full_time_salary, vacation_days, bonus)
VALUES (30107100907, 3500.00, 14, 500.00);

-- Part-time staff details
INSERT INTO PART_TIME (staffID, hourly_rate, shift_time)
VALUES (31214100905, 15.00, 'EVENING SHIFT (6PM-10PM)');

