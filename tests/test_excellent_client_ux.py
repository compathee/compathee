import importlib.util
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CLIENT_SCRIPT = ROOT / "excellent" / "scripts" / "combine_excellent_reports.py"


def load_client():
    spec = importlib.util.spec_from_file_location("excellent_client_combine", CLIENT_SCRIPT)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    sys.modules[spec.name] = module
    spec.loader.exec_module(module)
    return module


class ExcellentClientUxTests(unittest.TestCase):
    def test_ensure_work_dirs_creates_input_and_output(self):
        client = load_client()
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            input_dir, output_dir = client.ensure_work_dirs(root)
            self.assertEqual(input_dir, root / "input")
            self.assertEqual(output_dir, root / "output")
            self.assertTrue(input_dir.is_dir())
            self.assertTrue(output_dir.is_dir())
            again_input, again_output = client.ensure_work_dirs(root)
            self.assertEqual(again_input, input_dir)
            self.assertEqual(again_output, output_dir)

    def test_parse_args_defaults_input_dir_to_input(self):
        client = load_client()
        args = client.parse_args([])
        self.assertEqual(args.input_dir, Path("input"))
        self.assertEqual(args.output, Path("output/excellent-combined-report.xlsx"))

    def test_main_missing_input_dir_returns_nonzero(self):
        client = load_client()
        with tempfile.TemporaryDirectory() as tmp:
            missing = Path(tmp) / "no-xlsx-here"
            missing.mkdir()
            output = Path(tmp) / "out.xlsx"
            code = client.main(["--input-dir", str(missing), "--output", str(output)])
            self.assertNotEqual(code, 0)


if __name__ == "__main__":
    unittest.main()
