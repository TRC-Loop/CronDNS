import os
import shutil
from build.logger import logger
from gitignore_parser import parse_gitignore


def clean_output_dir(output_dir: str):
    if os.path.exists(output_dir):
        logger.info(f"Cleaning output directory: {output_dir}")
        shutil.rmtree(output_dir)
    os.makedirs(output_dir, exist_ok=True)


def copy_file(src_path: str, dest_path: str):
    os.makedirs(os.path.dirname(dest_path), exist_ok=True)
    shutil.copy2(src_path, dest_path)
    logger.info(f"Copied: {src_path} -> {dest_path}")


def get_gitignore_matcher(project_root: str):
    gitignore_path = os.path.join(project_root, ".gitignore")
    if os.path.exists(gitignore_path):
        logger.info(f"Using .gitignore from: {gitignore_path}")
        # The parse_gitignore base_dir should be the directory containing the .gitignore file.
        matches_func = parse_gitignore(gitignore_path, project_root)
        return lambda p: matches_func(os.path.normpath(p))
    return lambda x: False  # No .gitignore, so no files are ignored
