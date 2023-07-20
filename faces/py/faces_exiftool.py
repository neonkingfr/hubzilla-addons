import re
import logging


class ExifTool:

    def getDate(self, file):
        # Returns something like "1970-01-01T00:00:00+00:00" or "" (emtpy String)
        #
        # The following programms, modules do not find the DateTimeOriginal
        # in every image reliably. 
        # 1. exiftool, https://exiftool.org/
        #     proc = subprocess.Popen(['exiftool', '-DateTimeOriginal', '-offsettime', file], stdout=subprocess.PIPE)
        #     line = proc.stdout.readline()
        #     line = line.decode("utf-8")
        #     proc.kill()
        # 2. python package exif, https://pypi.org/project/exif/   
        # 3. from PIL import Image, ExifTags
        #
        # To proof that the above tools do not find the date the photo was taken...
        # - Use darktable. Export a photo. Choose the option to remove metadata.
        # - Search for the DateTimeOriginal by using one of the options above. 
        #   Result
        #   - No date is found by one of the tools above.
        #   - eog (eye of gnome) shows the date - somewhere hidden but it is there.
        #   - Open the image in a text editor. You will find the "DateTimeOriginal" there too.
        #   
        try:  # Open a filehandler for the filename.
            fh = open(file, "rb")
        except IOError as e:
            logging.error(str(e) + " - failed to read exif data from file " + file)
            return ""
            
        search_date = "DateTimeOriginal??????????????????????????"
        date = self.search(8388608, search_date, fh.read, fh.seek)
        
        #search_offset = "Offset??????"
        #offset = search(8388608, search_offset, fh.read, fh.seek)
        
        fh.close()
        
        if not date:
            return ""
    
        match = re.search(r'(\d+:\d+:\d+ \d+:\d+:\d+)',date)
        if match:
            exif_date = match.group()
            # print(exif_date)
            exif_date = exif_date.strip()
            exif_date = exif_date.replace(":", "-", 2)
            exif_date = exif_date.replace(" ", "T") # 2022-07-14T18:17:48
            exif_date = exif_date + "+00:00"
            return exif_date
        return ""
        

    def search(self, bsize, searchstring, fh_read, fh_seek):
        # based on https://github.com/Sepero/SearchBin
        pattern = [t.encode('utf-8') for t in searchstring.split("?")]
        len_pattern = len(b"?".join(pattern))  # Byte length of pattern.
        read_size = bsize - len_pattern  # Amount to read each loop.

        # Convert pattern into a regular expression for insane fast searching.
        pattern = [re.escape(p) for p in pattern]
        pattern = b".".join(pattern)
        # Grab regex search function directly to speed up function calls.
        regex_search = re.compile(pattern, re.DOTALL+re.MULTILINE).search

        offset = 0
        fh_seek(offset)   

        try:
            buffer = fh_read(len_pattern + read_size)  # Get initial buffer amount.
            match = regex_search(buffer)  # Search for a match in the buffer.
            # Set match to -1 if no match, else set it to the match position.

            if match:
                result = match.group()
                return result.decode()
            match = -1 if match == None else match.start()

            while True:  # Begin main loop for searching through a file.
                if match == -1:  # No match.
                    offset += read_size
                    # If end exists and we are beyond end, finish search.
                    buffer = buffer[read_size:]  # Erase front portion of buffer.
                    buffer += fh_read(read_size)  # Read more into the buffer.
                    # Search for next match in the buffer.
                    match = regex_search(buffer)
                    # If there is no match set match to -1, else the matching position.
                    match = -1 if match == None else match.start()
                else:  # Else- there was a match.
                    # Search for next match in the buffer.
                    match = regex_search(buffer, match+1)
                    if match:
                        result = match.group()
                        return result.decode()
                    match = -1 if match == None else match.start()

                # If finished reading input then end.
                if len(buffer) <= len_pattern:
                    return None

        except IOError as e:
            logging.error(str(e) + " - failed to search exif data in string (file)")
            return None
