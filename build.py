import os
import yaml
import zipfile
from tqdm.rich import tqdm  # Import tqdm for progress bar
from build.logger import logger
from build.utils import clean_output_dir, copy_file, get_gitignore_matcher
from build.templating import JinjaTemplater
import warnings
warnings.filterwarnings("ignore")

def zip_directory(source_dir, zip_path):
    # Collect all files to include
    files = []
    for root, _, filenames in os.walk(source_dir):
        for filename in filenames:
            filepath = os.path.join(root, filename)
            arcname = os.path.relpath(filepath, source_dir)
            files.append((filepath, arcname))
    total_files = len(files)

    with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as zipf:
        with tqdm(total=total_files, desc="Zipping") as pbar:
            for filepath, arcname in files:
                zipf.write(filepath, arcname)
                pbar.update(1)

def main():
    with open("config.yaml", "r", encoding="utf-8") as f:
        config = yaml.safe_load(f)

    build_config = config.get("build", {})
    output_dir = build_config.get("output_dir", "dist")
    src_dir = build_config.get("src_dir", "src")
    use_gitignore = build_config.get("use_gitignore", False)

    project_root = os.getcwd()

    clean_output_dir(output_dir)

    jinja_templater = JinjaTemplater(src_dir)

    cfg_context = {"cfg": config}

    gitignore_matcher = None
    if use_gitignore:
        gitignore_matcher = get_gitignore_matcher(project_root)

    # Count total files for progress bar
    total_files = 0
    for _, _, files in os.walk(src_dir):
        total_files += len(files)

    with tqdm(total=total_files, desc="Processing files") as pbar:
        for root, _, files in os.walk(src_dir):
            for file in files:
                src_path = os.path.join(root, file)
                relative_to_project_root = os.path.relpath(src_path, project_root)
                relative_to_src_dir = os.path.relpath(src_path, src_dir)

                if use_gitignore and gitignore_matcher and gitignore_matcher(relative_to_project_root):
                    logger.info(f"Ignoring file as per .gitignore: {src_path}")
                    pbar.update(1)
                    continue

                dest_path = os.path.join(output_dir, relative_to_src_dir)
                _, ext = os.path.splitext(file)

                try:
                    if ext in [".html", ".php", ".j2", ".scss", ".sass", ".css", ".template", ".txt", ".js", ".jinja2"]:
                        # Render with Jinja2
                        rendered_content = jinja_templater.render(relative_to_src_dir, cfg_context)
                        output_file_path = os.path.join(output_dir, relative_to_src_dir)
                        os.makedirs(os.path.dirname(output_file_path), exist_ok=True)

                        if ext in [".html", ".php", ".j2", ".template", ".txt", ".js", ".jinja2"]:
                            # For other templates, write rendered content
                            with open(output_file_path, "w", encoding="utf-8") as f:
                                f.write(rendered_content)
                            logger.info(f"Rendered: {src_path} -> {output_file_path}")

                        else:
                            # For other types, just write the rendered content
                            with open(output_file_path, "w", encoding="utf-8") as f:
                                f.write(rendered_content)
                            logger.info(f"Rendered: {src_path} -> {output_file_path}")


                    else:
                        # For other files, just copy
                        copy_file(src_path, dest_path)

                except Exception as e:
                    logger.error(f"Error processing {src_path}: {e}")
                    copy_file(src_path, dest_path)
                finally:
                    pbar.update(1)

    logger.info("Build process completed.")

    # After build, zip the dist directory
    dist_dir = output_dir
    zip_path = os.path.join(os.path.dirname(dist_dir), "dist.zip")
    zip_directory(dist_dir, zip_path)
    logger.info(f"Zipped {dist_dir} into {zip_path}")

if __name__ == "__main__":
    main()
