<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\HookFactories;

use Stecman\Component\Symfony\Console\BashCompletion\HookFactory;

class FishHookFactory extends HookFactory
{
    const SHELL = 'fish';

    private $script = <<<'END'
# FISH completion for %%program_path%%
exit 1;
END;

    /**
     * @inheritDoc
     */
    public function getShell()
    {
        return self::SHELL;
    }

    /**
     * @inheritDoc
     */
    public function getScript()
    {
        return $this->stripComments($this->script);
    }
}
