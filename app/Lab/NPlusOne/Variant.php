<?php

namespace App\Lab\NPlusOne;

use App\Lab\ClosureSource;
use Closure;

final readonly class Variant
{
    /**
     * @param  Closure(list<int>): string  $run  receives the fixed book ids, returns rendered HTML
     * @param  bool  $lazy  run inside LazyLoading::allowDuring() (the variant still has N+1)
     * @param  bool  $allowMissingAttributes  run with the strict "missing attribute" check off
     * @param  bool  $ranked  false for demos of failures (excluded from best/worst)
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $title,
        public string $description,
        public Closure $run,
        public bool $lazy = false,
        public bool $allowMissingAttributes = false,
        public bool $ranked = true,
    ) {}

    public function code(): string
    {
        return ClosureSource::body($this->run);
    }
}
