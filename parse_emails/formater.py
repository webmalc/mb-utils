import csv


def csv_generator(data, path):
    """
    Write csv file
    """
    with open(path, 'w+') as myfile:
        wr = csv.writer(myfile, quoting=csv.QUOTE_ALL)
        for row in data:
            wr.writerow(row)
