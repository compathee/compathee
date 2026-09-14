Excellent Books - объединение Excel отчетов
==========================================

Что внутри:

- run_combine_excellent_reports.bat
  Запуск для Windows двойным кликом.

- requirements.txt
  Python-зависимости.

- scripts/combine_excellent_reports.py
  Основной Python-скрипт.

- input/
  Необязательная папка для исходных .xlsx. Диалог выбора файлов
  может открыться здесь, но файлы можно выбрать из любой папки.

- output/
  Папка для готового отчета. По умолчанию файл сохраняется как
  output/excellent-combined-report.xlsx.


Как установить клиенту
----------------------

Скопируйте всю эту папку в:

  C:\temp\excellent

Итоговая структура должна быть:

  C:\temp\excellent\run_combine_excellent_reports.bat
  C:\temp\excellent\requirements.txt
  C:\temp\excellent\scripts\combine_excellent_reports.py
  C:\temp\excellent\input\
  C:\temp\excellent\output\


Как запускать
-------------

1. Убедитесь, что установлен Python 3.
2. Откройте:

   C:\temp\excellent\run_combine_excellent_reports.bat

3. Скрипт создаст папки input\ и output\, если их еще нет, и спросит:
   - какие исходные .xlsx файлы выбрать (можно из любой папки,
     не только из input\);
   - нужен ли справочник объектов .csv/.xlsx;
   - куда сохранить общий отчет (по умолчанию
     output\excellent-combined-report.xlsx);
   - какие префиксы объектных колонок использовать, по умолчанию HK_.

4. После успешного объединения появится окно с числом строк и
   полным путем к сохраненному файлу. Тот же путь печатается в консоли.

5. Если отменить выбор файлов или произойдет ошибка, появится
   понятное сообщение, а программа завершится с ошибкой.


Что делает скрипт
-----------------

Скрипт читает Excel-экспорты Standard/Excellent Books, находит объектные
колонки вроде HK_NOMME или HK_NÕMME и превращает их в строки общего отчета.

Пример:

  Account | Name    | HK_NOMME | HK_KESKLINN
  4000    | Revenue | 100      | 200

становится:

  source_file | sheet | object_code | object_name | amount | Account | Name
  Kinnisvara.xlsx | Report | HK_NOMME | Nomme LOV | 100 | 4000 | Revenue
  Kinnisvara.xlsx | Report | HK_KESKLINN | Kesklinn LOV | 200 | 4000 | Revenue


Optional справочник объектов
----------------------------

Можно выбрать .csv или .xlsx файл со столбцами:

  object_code,object_name
  HK_NOMME,Nomme LOV
  HK_KESKLINN,Kesklinn LOV

Если справочник не выбрать, object_name останется пустым.
