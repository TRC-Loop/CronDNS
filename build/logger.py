import logging
from rich.logging import RichHandler

# Clear the log file at the start
file_handler = logging.FileHandler('latest_build.log', mode='w')
file_handler.setLevel(logging.DEBUG)

logger = logging.getLogger("Hostanity Build")
logger.setLevel(logging.DEBUG)

# Set up RichHandler for console output
rich_handler = RichHandler(rich_tracebacks=True)
rich_handler.setFormatter(logging.Formatter("%(levelname)s: %(message)s"))

# Add handlers to the logger
logger.addHandler(file_handler)
logger.addHandler(rich_handler)
