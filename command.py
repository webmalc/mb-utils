#!/usr/bin/python
# -*- coding: utf-8 -*-
import os
import sys
from subprocess import call


def run_command(commands):
    ROOT_FOLDER = '/var/www/mbh'
    CONSOLE_FOLDER = 'bin/console'

    for console in [
            os.path.join(ROOT_FOLDER, d, CONSOLE_FOLDER)
            for d in os.listdir(ROOT_FOLDER)
            if os.path.isdir(os.path.join(ROOT_FOLDER, d))
    ]:
        try:
            for command in commands:
                print("\033[92mDir: {}. Command: {}\033[0m\n".format(console,
                                                                     command))
                call([console, command, '--env=prod'])
                print("\n")
        except OSError:
            print("No such file or directory: " + console)


if __name__ == '__main__':
    run_command(sys.argv[1:])
