import os
import re

class HalsteadAnalyzer:
    operators = [...]
    def analyze_file(self, filepath):
        # реализация аналогична halstead-analyzer.php
        pass

def analyze_all_php_files(root_dir):
    analyzer = HalsteadAnalyzer()
    results = []
    for dirpath, dirnames, filenames in os.walk(root_dir):
        for file in filenames:
            if file.endswith('.php'):
                results.append(analyzer.analyze_file(os.path.join(dirpath, file)))
    return results