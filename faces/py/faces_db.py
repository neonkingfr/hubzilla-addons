import mysql.connector
from mysql.connector import Error
import sys
import logging


class Database:

    def __init__(self, logdata):
        self.log_all = logdata
        self.conn = None
        self.cursor = None

    def connect(self, db_user, db_pass, db_host, db_db):
        try:
            self.conn = mysql.connector.connect(
                user=db_user,
                password=db_pass,
                host=db_host,
                database=db_db)
            if self.conn.is_connected():
                logging.debug('Connected to MySQL database')
            else:
                logging.error('Failed to connect to database. Exit...')
                sys.exit(1)
        except Error as e:
            logging.error('Error connecting to connect to database. Exit...')
            logging.error(e)
            sys.exit(1)
        self.cursor = self.conn.cursor()

    def update(self, query, data):
        if self.log_all:
            logging.debug("query: " + str(query))
            logging.debug("data: " + str(data))
        self.cursor.execute(query, data)
        self.conn.commit()

    def select(self, query, data):
        if self.log_all:
            logging.debug("query: " + str(query))
            logging.debug("data: " + str(data))
        self.cursor.execute(query, data)
        rows = self.cursor.fetchall()
        if self.log_all:
            logging.debug("rows: " + str(rows))
        return rows

    def close(self):
        self.cursor.close()
        self.conn.close()
        logging.debug('Closed connection to MySQL database')
