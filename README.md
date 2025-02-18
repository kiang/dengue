# Taiwan Dengue Fever Cases Tracker

This project collects and visualizes Dengue Fever case data from Taiwan Centers for Disease Control (CDC). The visualization is hosted at [https://tainan.olc.tw/p/dengue/](https://tainan.olc.tw/p/dengue/).

## Overview

The project automatically fetches daily Dengue Fever case data from Taiwan CDC's open data platform, processes it, and generates visualizations to help track both local and imported cases across different cities and districts in Taiwan.

## Data Source

- Data is sourced from Taiwan CDC's open data platform
- Dataset: [Dengue_Daily.csv](https://od.cdc.gov.tw/eic/Dengue_Daily.csv)
- The data includes both local and imported cases
- Geographic coverage: All cities and counties in Taiwan

## Features

- Daily data updates
- Separate tracking of local and imported cases
- Detailed breakdown by administrative regions (cities/counties)
- Village/neighborhood (里) level case distribution
- Historical data preservation and visualization
- JSON output for easy integration with other applications

## Data Structure

The processed data is organized as follows:
- Daily data is stored in CSV format, separated by year and city
- Summary data is stored in JSON format
- Village/neighborhood level data is available in `cunli.json`

## Requirements

- PHP 7.0 or higher
- Write permissions for the `docs/daily` directory

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

The data from Taiwan CDC is released under a CC-BY compatible license. When using the data, please attribute: "Data source: Taiwan Centers for Disease Control"

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Contact

For questions or suggestions, please open an issue in this repository.

---
*Note: This project is not officially affiliated with Taiwan CDC.* 