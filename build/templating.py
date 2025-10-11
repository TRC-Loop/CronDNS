from jinja2 import Environment, FileSystemLoader
from build.logger import logger


class JinjaTemplater:
    def __init__(self, src_dir: str):
        self.env = Environment(loader=FileSystemLoader(src_dir))

    def render(self, template_path: str, context: dict) -> str:
        logger.info(f"Rendering Jinja2 template: {template_path}")
        template = self.env.get_template(template_path)
        return template.render(context)
