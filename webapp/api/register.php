<?php
require __DIR__ . '/../config/bootstrap.php';

// Self-service registration is disabled for this app -- it's a closed group.
// New accounts are added directly by an admin. Left in place (rather than
// deleted) so re-enabling it later is a one-line revert, not a rebuild.
json_error('Registration is closed. Contact the admin for access.', 403);
