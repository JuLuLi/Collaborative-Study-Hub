<?php
function vark_label($v) {
  return match($v) {
    'V' => 'Visual Focus',
    'A' => 'Auditory Focus',
    'R' => 'Reading & Writing Focus',
    'K' => 'Kinesthetic Focus',
    default => 'Unknown'
  };
}
function vark_badge_class($v) {
  return match($v) {
    'V' => 'badge-v',
    'A' => 'badge-a',
    'R' => 'badge-r',
    'K' => 'badge-k',
    default => ''
  };
}
function vark_full_name($v) {
  return match($v) {
    'V' => 'Visual Learner',
    'A' => 'Auditory Learner',
    'R' => 'Reading/Writing Learner',
    'K' => 'Kinesthetic Learner',
    default => 'Learner'
  };
}
function vark_description($v) {
  return match($v) {
    'V' => 'You learn best with diagrams, charts, images, and visual explanations.',
    'A' => 'You learn best through discussions, lectures, and verbal explanations.',
    'R' => 'You learn best by reading textbooks, taking notes, and writing summaries.',
    'K' => 'You learn best with hands-on activities, experiments, and physical tasks.',
    default => 'Discover your unique learning style to get better matches.'
  };
}
function vark_icon($v) {
  return match($v) {
    'V' => '📊',
    'A' => '🎧',
    'R' => '📚',
    'K' => '🔬',
    default => '🧠'
  };
}
?>