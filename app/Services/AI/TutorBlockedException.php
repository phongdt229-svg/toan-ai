<?php

namespace App\Services\AI;

use RuntimeException;

/** AI Tutor từ chối vì lý do sư phạm / chống gian lận — message hiện thẳng cho học sinh. */
class TutorBlockedException extends RuntimeException {}
