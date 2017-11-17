import re

from bs4 import BeautifulSoup
from imbox import Imbox

from .settings import EMAIL, EMAIL_FOLDER, EMAIL_FROM, PASSWORD, SMTP


def get_emails(verbose=False):
    """
    Get emails from server
    """
    with Imbox(
            SMTP, username=EMAIL, password=PASSWORD, ssl=True,
            ssl_context=None) as imbox:
        status, folders_with_additional_info = imbox.folders()

        messages = imbox.messages(folder=EMAIL_FOLDER, sent_from=EMAIL_FROM)
        i = 1
        for uiid, message in messages:
            if verbose:
                print('process email #{}'.format(i))
            i += 1
            yield message.body['html'].pop()


def parse_emails(emails):
    """
    Parse email content
    """
    yield ['name', 'email', 'phone']

    for html in emails:
        soup = BeautifulSoup(html, 'html.parser')
        header = soup.find('strong', text='Email:')
        email, phone, name = None, None, None
        if header:
            email = header.find_next('a').text.strip()
        header = soup.find('strong', text='Телефон:')
        if header:
            phone = header.next_sibling.strip()
        header = soup.find(text=re.compile('.*и посетителем сайта.*'))
        if header:
            name = header.next_sibling.text.strip()

        values = [name, email, phone]
        if any(values):
            yield values
