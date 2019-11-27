<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\HookFactories;

use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Stecman\Component\Symfony\Console\BashCompletion\HookFactory;

class BashHookFactory extends HookFactory
{
    const SHELL = 'bash';

    private $script = <<<'END'
# BASH completion for %%program_path%%
function %%function_name%% {

    # Copy BASH's completion variables to the ones the completion command expects
    # These line up exactly as the library was originally designed for BASH
    local CMDLINE_CONTENTS="$COMP_LINE";
    local CMDLINE_CURSOR_INDEX="$COMP_POINT";
    local CMDLINE_WORDBREAKS="$COMP_WORDBREAKS";

    export CMDLINE_CONTENTS CMDLINE_CURSOR_INDEX CMDLINE_WORDBREAKS;

    local RESULT STATUS;

    # Force splitting by newline instead of default delimiters
    local IFS=$'\n';

    RESULT="$(%%completion_command%% </dev/null)";
    STATUS=$?;

    local cur mail_check_backup;

    mail_check_backup=$MAILCHECK;
    MAILCHECK=-1;

    _get_comp_words_by_ref -n : cur;

    # Check if shell provided path completion is requested
    # @see Completion\ShellPathCompletion
    if [ $STATUS -eq 200 ]; then
        # Turn file/dir completion on temporarily and give control back to BASH
        compopt -o default;
        return 0;

    # Bail out if PHP didn't exit cleanly
    elif [ $STATUS -ne 0 ]; then
        echo -e "$RESULT";
        return $?;
    fi;

    COMPREPLY=(`compgen -W "$RESULT" -- $cur`);

    __ltrim_colon_completions "$cur";

    MAILCHECK=mail_check_backup;
};

if [ "$(type -t _get_comp_words_by_ref)" == "function" ]; then
    complete -F %%function_name%% "%%program_name%%";
else
    >&2 echo "Completion was not registered for %%program_name%%:";
    >&2 echo "The 'bash-completion' package is required but doesn't appear to be installed.";
fi;
END;

    /**
     * BASH requires special escaping for multi-word and special character results.
     * This emulates registering completion with`-o filenames`, without side-effects like dir name slashes.
     *
     * @param string            $result
     * @param CompletionContext $context
     *
     * @return string
     */
    public function escape($result, $context)
    {
        $wordStart = substr($context->getRawCurrentWord(), 0, 1);

        if ($wordStart === "'") {
            // If the current word is single-quoted, escape any single quotes in the result
            $result = str_replace("'", "\\'", $result);
        } else {
            if ($wordStart === '"') {
                // If the current word is double-quoted, escape any double quotes in the result
                $result = str_replace('"', '\\"', $result);
            } else {
                // Otherwise assume the string is unquoted and word breaks should be escaped
                $result = preg_replace('/([\s\'"\\\\])/', '\\\\$1', $result);
            }
        }

        // Escape output to prevent special characters being lost when passing results to compgen
        return escapeshellarg($result);
    }

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
