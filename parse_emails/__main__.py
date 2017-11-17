from .formater import csv_generator
from .imap import get_emails, parse_emails
from .user_input import get_file_path

args = get_file_path()
csv_generator(parse_emails(get_emails(verbose=True)), args.path)
