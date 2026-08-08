<?php
// booking_hotel.php
//
// This used to be a near-identical duplicate of book_hotel.php (same
// booking logic maintained in two places, which is exactly how bugs
// like the missing CSRF protection stayed unfixed in one copy after
// being fixed in the other). Consolidated into a single redirect so
// any existing links/bookmarks to this URL keep working, but there is
// only ONE real booking-logic file to maintain going forward.

$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: book_hotel.php' . ($qs ? '?' . $qs : ''));
exit();