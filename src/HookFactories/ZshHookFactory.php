<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\HookFactories;

use Stecman\Component\Symfony\Console\BashCompletion\HookFactory;

class ZshHookFactory extends HookFactory
{
    const SHELL = 'zsh';

    private $script = <<<'END'
# ZSH completion for %%program_path%%
function %%function_name%% {
    local -x CMDLINE_CONTENTS="$words";
    local -x CMDLINE_CURSOR_INDEX;
    (( CMDLINE_CURSOR_INDEX = ${#${(j. .)words[1,CURRENT]}} ));

    local RESULT STATUS;
    RESULT=("${(@f)$( %%completion_command%% )}");
    STATUS=$?;

    # Check if shell provided path completion is requested
    # @see Completion\ShellPathCompletion
    if [ $STATUS -eq 200 ]; then
        _path_files;
        return 0;

    # Bail out if PHP didn't exit cleanly
    elif [ $STATUS -ne 0 ]; then
        echo -e "$RESULT";
        return $?;
    fi;

    compadd -- $RESULT;
};

compdef %%function_name%% "%%program_name%%";
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
