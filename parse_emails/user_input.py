import argparse
import os
from time import time

from .settings import DEFAULT_FILE_PATH


def get_file_path():
    """
    Get user args
    """

    class PathAction(argparse.Action):
        def __call__(self, parser, namespace, values, option_string=None):
            if os.path.exists(values):
                prefix = values.rsplit('.')[0]
                extension = values.rsplit('.')[1]
                values = '{}_{}.{}'.format(prefix, time(), extension)
            elif os.access(os.path.dirname(values), os.W_OK):
                pass
            else:
                raise ValueError("Invalid path!")
            setattr(namespace, self.dest, os.path.expanduser(values))

    parser = argparse.ArgumentParser()
    parser.add_argument(
        '--path',
        type=str,
        action=PathAction,
        default=DEFAULT_FILE_PATH,
        help='path to file ({})'.format(DEFAULT_FILE_PATH))
    return parser.parse_args()
