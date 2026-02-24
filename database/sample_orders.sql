-- ============================================
-- Sample Orders Data for PrintFlow (100 rows)
-- Spread across Feb 1-19, 2026
-- Customers: 1-4 | Payment Methods: 1-3
-- ============================================
SET FOREIGN_KEY_CHECKS = 0;
INSERT INTO `orders` (
        `customer_id`,
        `order_date`,
        `total_amount`,
        `status`,
        `payment_status`,
        `payment_method_id`,
        `payment_reference`,
        `discount_id`
    )
VALUES -- Feb 1
    (
        1,
        '2026-02-01 01:30:00',
        700.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        2,
        '2026-02-01 03:00:00',
        350.00,
        'Completed',
        'Paid',
        2,
        'GC-20260201A',
        NULL
    ),
    (
        3,
        '2026-02-01 05:15:00',
        1200.00,
        'Completed',
        'Paid',
        1,
        NULL,
        1
    ),
    (
        4,
        '2026-02-01 07:00:00',
        150.00,
        'Completed',
        'Paid',
        3,
        'MY-20260201A',
        NULL
    ),
    (
        1,
        '2026-02-01 09:45:00',
        500.00,
        'Completed',
        'Paid',
        2,
        'GC-20260201B',
        NULL
    ),
    -- Feb 2
    (
        2,
        '2026-02-02 01:00:00',
        950.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        3,
        '2026-02-02 02:30:00',
        300.00,
        'Completed',
        'Paid',
        3,
        'MY-20260202A',
        2
    ),
    (
        1,
        '2026-02-02 04:00:00',
        1500.00,
        'Completed',
        'Paid',
        2,
        'GC-20260202A',
        NULL
    ),
    (
        4,
        '2026-02-02 06:30:00',
        450.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        2,
        '2026-02-02 08:00:00',
        200.00,
        'Completed',
        'Paid',
        1,
        NULL,
        1
    ),
    -- Feb 3
    (
        3,
        '2026-02-03 01:00:00',
        800.00,
        'Completed',
        'Paid',
        2,
        'GC-20260203A',
        NULL
    ),
    (
        1,
        '2026-02-03 03:15:00',
        1100.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        4,
        '2026-02-03 05:30:00',
        250.00,
        'Completed',
        'Paid',
        3,
        'MY-20260203A',
        NULL
    ),
    (
        2,
        '2026-02-03 07:00:00',
        600.00,
        'Completed',
        'Paid',
        1,
        NULL,
        2
    ),
    (
        3,
        '2026-02-03 09:00:00',
        350.00,
        'Cancelled',
        'Refunded',
        2,
        'GC-20260203B',
        NULL
    ),
    -- Feb 4
    (
        1,
        '2026-02-04 01:30:00',
        1800.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        2,
        '2026-02-04 03:00:00',
        400.00,
        'Completed',
        'Paid',
        2,
        'GC-20260204A',
        1
    ),
    (
        4,
        '2026-02-04 05:00:00',
        750.00,
        'Completed',
        'Paid',
        3,
        'MY-20260204A',
        NULL
    ),
    (
        3,
        '2026-02-04 07:30:00',
        550.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-04 09:00:00',
        300.00,
        'Completed',
        'Paid',
        2,
        'GC-20260204B',
        NULL
    ),
    -- Feb 5
    (
        2,
        '2026-02-05 01:00:00',
        1250.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        3,
        '2026-02-05 02:45:00',
        450.00,
        'Completed',
        'Paid',
        3,
        'MY-20260205A',
        NULL
    ),
    (
        4,
        '2026-02-05 04:30:00',
        900.00,
        'Completed',
        'Paid',
        2,
        'GC-20260205A',
        2
    ),
    (
        1,
        '2026-02-05 06:00:00',
        200.00,
        'Pending',
        'Unpaid',
        1,
        NULL,
        NULL
    ),
    (
        2,
        '2026-02-05 08:30:00',
        650.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    -- Feb 6
    (
        3,
        '2026-02-06 01:30:00',
        1400.00,
        'Completed',
        'Paid',
        2,
        'GC-20260206A',
        NULL
    ),
    (
        4,
        '2026-02-06 03:00:00',
        350.00,
        'Completed',
        'Paid',
        1,
        NULL,
        1
    ),
    (
        1,
        '2026-02-06 05:15:00',
        800.00,
        'Completed',
        'Paid',
        3,
        'MY-20260206A',
        NULL
    ),
    (
        2,
        '2026-02-06 07:00:00',
        500.00,
        'Processing',
        'Paid',
        2,
        'GC-20260206B',
        NULL
    ),
    (
        3,
        '2026-02-06 09:30:00',
        150.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    -- Feb 7
    (
        4,
        '2026-02-07 01:00:00',
        2200.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-07 02:30:00',
        600.00,
        'Completed',
        'Paid',
        2,
        'GC-20260207A',
        2
    ),
    (
        2,
        '2026-02-07 04:00:00',
        450.00,
        'Completed',
        'Paid',
        3,
        'MY-20260207A',
        NULL
    ),
    (
        3,
        '2026-02-07 06:30:00',
        1050.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-07 08:00:00',
        300.00,
        'Cancelled',
        'Refunded',
        2,
        'GC-20260207B',
        NULL
    ),
    -- Feb 8
    (
        4,
        '2026-02-08 01:15:00',
        750.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        2,
        '2026-02-08 03:00:00',
        1600.00,
        'Completed',
        'Paid',
        2,
        'GC-20260208A',
        NULL
    ),
    (
        3,
        '2026-02-08 05:30:00',
        400.00,
        'Completed',
        'Paid',
        3,
        'MY-20260208A',
        1
    ),
    (
        1,
        '2026-02-08 07:00:00',
        550.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        4,
        '2026-02-08 09:00:00',
        250.00,
        'Pending',
        'Unpaid',
        1,
        NULL,
        NULL
    ),
    -- Feb 9
    (
        2,
        '2026-02-09 01:00:00',
        1350.00,
        'Completed',
        'Paid',
        2,
        'GC-20260209A',
        NULL
    ),
    (
        3,
        '2026-02-09 02:45:00',
        500.00,
        'Completed',
        'Paid',
        1,
        NULL,
        2
    ),
    (
        1,
        '2026-02-09 04:30:00',
        850.00,
        'Completed',
        'Paid',
        3,
        'MY-20260209A',
        NULL
    ),
    (
        4,
        '2026-02-09 06:00:00',
        200.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        2,
        '2026-02-09 08:30:00',
        700.00,
        'Completed',
        'Paid',
        2,
        'GC-20260209B',
        NULL
    ),
    -- Feb 10
    (
        3,
        '2026-02-10 01:30:00',
        1900.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-10 03:00:00',
        350.00,
        'Completed',
        'Paid',
        2,
        'GC-20260210A',
        1
    ),
    (
        4,
        '2026-02-10 05:00:00',
        600.00,
        'Completed',
        'Paid',
        3,
        'MY-20260210A',
        NULL
    ),
    (
        2,
        '2026-02-10 07:30:00',
        1100.00,
        'Processing',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        3,
        '2026-02-10 09:00:00',
        450.00,
        'Completed',
        'Paid',
        2,
        'GC-20260210B',
        NULL
    ),
    -- Feb 11
    (
        1,
        '2026-02-11 01:00:00',
        2500.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        4,
        '2026-02-11 02:30:00',
        300.00,
        'Completed',
        'Paid',
        3,
        'MY-20260211A',
        NULL
    ),
    (
        2,
        '2026-02-11 04:15:00',
        800.00,
        'Completed',
        'Paid',
        2,
        'GC-20260211A',
        2
    ),
    (
        3,
        '2026-02-11 06:00:00',
        550.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-11 08:00:00',
        150.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    -- Feb 12
    (
        4,
        '2026-02-12 01:30:00',
        1750.00,
        'Completed',
        'Paid',
        2,
        'GC-20260212A',
        NULL
    ),
    (
        2,
        '2026-02-12 03:00:00',
        400.00,
        'Completed',
        'Paid',
        1,
        NULL,
        1
    ),
    (
        3,
        '2026-02-12 05:30:00',
        950.00,
        'Completed',
        'Paid',
        3,
        'MY-20260212A',
        NULL
    ),
    (
        1,
        '2026-02-12 07:00:00',
        650.00,
        'Completed',
        'Paid',
        2,
        'GC-20260212B',
        NULL
    ),
    (
        4,
        '2026-02-12 09:00:00',
        200.00,
        'Cancelled',
        'Refunded',
        1,
        NULL,
        NULL
    ),
    -- Feb 13
    (
        2,
        '2026-02-13 01:00:00',
        1300.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        3,
        '2026-02-13 02:30:00',
        500.00,
        'Completed',
        'Paid',
        2,
        'GC-20260213A',
        NULL
    ),
    (
        1,
        '2026-02-13 04:00:00',
        750.00,
        'Completed',
        'Paid',
        3,
        'MY-20260213A',
        2
    ),
    (
        4,
        '2026-02-13 06:30:00',
        350.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        2,
        '2026-02-13 08:00:00',
        1000.00,
        'Completed',
        'Paid',
        2,
        'GC-20260213B',
        NULL
    ),
    -- Feb 14 (Valentine's Day - busier)
    (
        1,
        '2026-02-14 00:30:00',
        2800.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        3,
        '2026-02-14 02:00:00',
        600.00,
        'Completed',
        'Paid',
        2,
        'GC-20260214A',
        1
    ),
    (
        2,
        '2026-02-14 03:30:00',
        1500.00,
        'Completed',
        'Paid',
        3,
        'MY-20260214A',
        NULL
    ),
    (
        4,
        '2026-02-14 05:00:00',
        450.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-14 06:30:00',
        900.00,
        'Completed',
        'Paid',
        2,
        'GC-20260214B',
        NULL
    ),
    (
        3,
        '2026-02-14 08:00:00',
        350.00,
        'Completed',
        'Paid',
        1,
        NULL,
        2
    ),
    (
        2,
        '2026-02-14 09:30:00',
        1200.00,
        'Completed',
        'Paid',
        3,
        'MY-20260214B',
        NULL
    ),
    -- Feb 15
    (
        4,
        '2026-02-15 01:00:00',
        550.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-15 03:15:00',
        1650.00,
        'Completed',
        'Paid',
        2,
        'GC-20260215A',
        NULL
    ),
    (
        3,
        '2026-02-15 05:00:00',
        300.00,
        'Ready for Pickup',
        'Paid',
        1,
        NULL,
        1
    ),
    (
        2,
        '2026-02-15 07:00:00',
        800.00,
        'Completed',
        'Paid',
        3,
        'MY-20260215A',
        NULL
    ),
    (
        4,
        '2026-02-15 09:00:00',
        400.00,
        'Completed',
        'Paid',
        2,
        'GC-20260215B',
        NULL
    ),
    -- Feb 16
    (
        1,
        '2026-02-16 01:30:00',
        1100.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        2,
        '2026-02-16 03:00:00',
        750.00,
        'Completed',
        'Paid',
        2,
        'GC-20260216A',
        2
    ),
    (
        3,
        '2026-02-16 05:00:00',
        450.00,
        'Processing',
        'Paid',
        3,
        'MY-20260216A',
        NULL
    ),
    (
        4,
        '2026-02-16 07:30:00',
        250.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-16 09:00:00',
        600.00,
        'Completed',
        'Paid',
        2,
        'GC-20260216B',
        NULL
    ),
    -- Feb 17
    (
        2,
        '2026-02-17 01:00:00',
        1850.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        3,
        '2026-02-17 02:45:00',
        500.00,
        'Completed',
        'Paid',
        3,
        'MY-20260217A',
        1
    ),
    (
        4,
        '2026-02-17 04:30:00',
        350.00,
        'Completed',
        'Paid',
        2,
        'GC-20260217A',
        NULL
    ),
    (
        1,
        '2026-02-17 06:00:00',
        1200.00,
        'Pending',
        'Unpaid',
        1,
        NULL,
        NULL
    ),
    (
        2,
        '2026-02-17 08:30:00',
        650.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    -- Feb 18
    (
        3,
        '2026-02-18 01:30:00',
        2100.00,
        'Completed',
        'Paid',
        2,
        'GC-20260218A',
        NULL
    ),
    (
        4,
        '2026-02-18 03:00:00',
        400.00,
        'Completed',
        'Paid',
        1,
        NULL,
        2
    ),
    (
        1,
        '2026-02-18 05:15:00',
        900.00,
        'Completed',
        'Paid',
        3,
        'MY-20260218A',
        NULL
    ),
    (
        2,
        '2026-02-18 07:00:00',
        550.00,
        'Ready for Pickup',
        'Paid',
        2,
        'GC-20260218B',
        NULL
    ),
    (
        3,
        '2026-02-18 09:00:00',
        300.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    -- Feb 19
    (
        4,
        '2026-02-19 01:00:00',
        1500.00,
        'Completed',
        'Paid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-19 02:30:00',
        700.00,
        'Completed',
        'Paid',
        2,
        'GC-20260219A',
        1
    ),
    (
        2,
        '2026-02-19 04:00:00',
        850.00,
        'Processing',
        'Paid',
        3,
        'MY-20260219A',
        NULL
    ),
    (
        3,
        '2026-02-19 06:30:00',
        450.00,
        'Pending',
        'Unpaid',
        1,
        NULL,
        NULL
    ),
    (
        1,
        '2026-02-19 08:00:00',
        1150.00,
        'Completed',
        'Paid',
        2,
        'GC-20260219B',
        NULL
    );
SET FOREIGN_KEY_CHECKS = 1;