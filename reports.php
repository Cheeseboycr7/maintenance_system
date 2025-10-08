<?php
require_once 'auth.php';
redirectIfNotLoggedIn();

$database = new Database();
$db = $database->getConnection();

// Get employees for filtering
$employees = $db->query("SELECT * FROM employees ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Initialize variables
$report_data = [];
$report_type = $_POST['report_type'] ?? 'maintenance_summary';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? date('Y-m-d');
$employee_id = $_POST['employee_id'] ?? '';
$error = '';

// Function to generate reports safely
function generateReport($db, $report_type, $start_date, $end_date, $employee_id) {
    $params = [];
    
    // Validate date format if provided
    if (!empty($start_date) && !validateDate($start_date)) {
        throw new Exception("Invalid start date format");
    }
    
    if (!empty($end_date) && !validateDate($end_date)) {
        throw new Exception("Invalid end date format");
    }
    
    // Build query based on report type
    switch ($report_type) {
        case 'employee_performance':
            $query = "
                SELECT e.name, 
                       COUNT(t.id) as total_tasks,
                       SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
                       ROUND(COALESCE(SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(t.id), 0), 0), 0) as completion_rate
                FROM employees e
                LEFT JOIN tasks t ON e.id = t.assigned_to
            ";
            
            $conditions = [];
            if (!empty($start_date)) {
                $conditions[] = "t.created_at >= :start_date";
                $params[':start_date'] = $start_date;
            }
            if (!empty($end_date)) {
                $conditions[] = "t.created_at <= :end_date";
                $params[':end_date'] = $end_date;
            }
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }
            
            $query .= " GROUP BY e.id ORDER BY completion_rate DESC";
            break;
            
        case 'task_completion':
            $query = "
                SELECT t.title, t.priority, t.status, t.progress, t.due_date, 
                       e.name as assigned_name, t.created_at, t.completed_at
                FROM tasks t
                LEFT JOIN employees e ON t.assigned_to = e.id
                WHERE 1=1
            ";
            
            if (!empty($start_date)) {
                $query .= " AND t.created_at >= :start_date";
                $params[':start_date'] = $start_date;
            }
            if (!empty($end_date)) {
                $query .= " AND t.created_at <= :end_date";
                $params[':end_date'] = $end_date;
            }
            if (!empty($employee_id)) {
                $query .= " AND t.assigned_to = :employee_id";
                $params[':employee_id'] = $employee_id;
            }
            
            $query .= " ORDER BY t.created_at DESC";
            break;
            
        case 'maintenance_summary':
        default:
            $query = "
                SELECT 
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
                    ROUND(AVG(progress), 2) as avg_progress
                FROM tasks
                WHERE 1=1
            ";
            
            if (!empty($start_date)) {
                $query .= " AND created_at >= :start_date";
                $params[':start_date'] = $start_date;
            }
            if (!empty($end_date)) {
                $query .= " AND created_at <= :end_date";
                $params[':end_date'] = $end_date;
            }
            break;
    }
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Date validation function
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

// Handle report generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_report'])) {
    try {
        $report_type = htmlspecialchars($_POST['report_type']);
        $start_date = !empty($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : '';
        $end_date = !empty($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : date('Y-m-d');
        $employee_id = !empty($_POST['employee_id']) ? intval($_POST['employee_id']) : '';
        
        $report_data = generateReport($db, $report_type, $start_date, $end_date, $employee_id);
    } catch (Exception $e) {
        $error = "Error generating report: " . $e->getMessage();
    }
}

// Handle report download
if (isset($_GET['download'])) {
    try {
        $download_type = $_GET['download'];
        $format = $_GET['format'] ?? 'csv';
        $dl_report_type = htmlspecialchars($_GET['report_type']);
        $dl_start_date = !empty($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : '';
        $dl_end_date = !empty($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : date('Y-m-d');
        $dl_employee_id = !empty($_GET['employee_id']) ? intval($_GET['employee_id']) : '';
        
        $download_data = generateReport($db, $dl_report_type, $dl_start_date, $dl_end_date, $dl_employee_id);
        
        // Generate download file
        if ($format == 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=maintenance_report_' . date('Y-m-d') . '.csv');
            
            $output = fopen('php://output', 'w');
            
            // Add headers
            if (!empty($download_data)) {
                fputcsv($output, array_keys($download_data[0]));
                
                // Add data
                foreach ($download_data as $row) {
                    fputcsv($output, $row);
                }
            }
            
            fclose($output);
            exit();
        } elseif ($format == 'pdf') {
            // Generate PDF using DomPDF
            require_once './dompdf-3.0.1/dompdf/vendor/autoload.php'; // Include Composer's autoloader
            
            // Create PDF instance
            $dompdf = new Dompdf\Dompdf();
            $dompdf->setPaper('A4', 'portrait');
            
            // Generate HTML content for PDF
            $html = generatePdfHtml($dl_report_type, $download_data, $dl_start_date, $dl_end_date);
            
            // Load HTML content
            $dompdf->loadHtml($html);
            
            // Render PDF
            $dompdf->render();
            
            // Output PDF
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="maintenance_report_' . date('Y-m-d') . '.pdf"');
            echo $dompdf->output();
            exit();
        }
    } catch (Exception $e) {
        $error = "Error downloading report: " . $e->getMessage();
    }
}

// Function to generate HTML for PDF
function generatePdfHtml($report_type, $data, $start_date, $end_date) {
    $date_range = '';
    if (!empty($start_date) && !empty($end_date)) {
        $date_range = date('M j, Y', strtotime($start_date)) . ' to ' . date('M j, Y', strtotime($end_date));
    } elseif (!empty($start_date)) {
        $date_range = 'From ' . date('M j, Y', strtotime($start_date));
    } elseif (!empty($end_date)) {
        $date_range = 'Until ' . date('M j, Y', strtotime($end_date));
    }
    
    // Use absolute path for the logo
    $logoPath = realpath('C:/xampp/htdocs/maintenance_system/twr.png');
    $logoHtml = '';
    
    if ($logoPath && file_exists($logoPath)) {
        // Use absolute file path with file:// protocol
        $logoHtml = '<img src="file://' . $logoPath . '" class="company-logo" alt="Company Logo">';
    } else {
        // Fallback to text if logo not found
        $logoHtml = '<div style="font-size: 24px; font-weight: bold;">Maintenance System</div>';
    }
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Maintenance Report</title>
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
            .header { text-align: center; margin-bottom: 20px; }
            .company-logo { height: 70px; margin-bottom: 10px; }
            .report-title { font-size: 20px; font-weight: bold; margin-bottom: 5px; }
            .report-date { font-size: 14px; color: #666; margin-bottom: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .signature-area { margin-top: 50px; }
            .signature-line { width: 300px; border-top: 1px solid #000; margin-bottom: 5px; }
            .footer { margin-top: 50px; font-size: 10px; color: #666; text-align: center; }
        </style>
    </head>
    <body>
        <div class="header">
            ' . $logoHtml . '
            <div class="report-title">Maintenance Management System Report</div>
            <div class="report-date">' . ($date_range ? 'Period: ' . $date_range : '') . '</div>
        </div>
    ';
    
    if (!empty($data)) {
        $html .= '<table>';
        $html .= '<thead><tr>';
        foreach (array_keys($data[0]) as $column) {
            $html .= '<th>' . ucwords(str_replace('_', ' ', $column)) . '</th>';
        }
        $html .= '</tr></thead>';
        
        $html .= '<tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach ($row as $key => $value) {
                if (strpos($key, 'progress') !== false && is_numeric($value)) {
                    $html .= '<td>' . $value . '%</td>';
                } elseif (strpos($key, 'date') !== false && !empty($value)) {
                    $html .= '<td>' . date('M j, Y', strtotime($value)) . '</td>';
                } else {
                    $html .= '<td>' . htmlspecialchars($value) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
    } else {
        $html .= '<p>No data available for the selected criteria.</p>';
    }
    
    $html .= '
        <div class="signature-area">
            <div>Prepared By: _________________________</div>
            <div class="signature-line"></div>
            <div>Date: ' . date('M j, Y') . '</div>
        </div>
        
        <div class="signature-area">
            <div>Approved By: _________________________</div>
            <div class="signature-line"></div>
            <div>Date: _________________________</div>
        </div>
        
        <div class="footer">
            Generated on ' . date('M j, Y \a\t H:i') . ' | Maintenance Management System
        </div>
    </body>
    </html>';
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Maintenance Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .report-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .download-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }
        .download-btn.csv {
            background-color: #28a745;
            color: white;
        }
        .download-btn.csv:hover {
            background-color: #218838;
        }
        .download-btn.pdf {
            background-color: #dc3545;
            color: white;
        }
        .download-btn.pdf:hover {
            background-color: #c82333;
        }
        .download-btn.excel {
            background-color: #17a2b8;
            color: white;
        }
        .download-btn.excel:hover {
            background-color: #138496;
        }
        .tooltip-icon {
            color: #6c757d;
            margin-left: 5px;
            cursor: pointer;
        }
        .card {
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        .card-title {
            color: #5a5c69;
            font-weight: 600;
        }
        .table th {
            border-top: none;
            font-weight: 600;
            color: #5a5c69;
            background-color: #f8f9fc;
        }
        .progress {
            height: 10px;
        }
        .badge {
            font-weight: 500;
        }
        .status-completed {
            background-color: #1cc88a;
            color: white;
        }
        .status-inprogress {
            background-color: #f6c23e;
            color: #2c2929;
        }
        .status-pending {
            background-color: #e74a3b;
            color: white;
        }
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        .spinner-border {
            width: 3rem;
            height: 3rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <?php include 'header.php'; ?>
        
        <div class="content">
            <h2 class="mb-4">Reports</h2>
            
            <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Error!</strong> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Generate Report</h5>
                            <form method="POST" id="reportForm">
                                <div class="mb-3">
                                    <label class="form-label">Report Type</label>
                                    <div class="d-flex align-items-center">
                                        <select class="form-select" name="report_type" required>
                                            <option value="employee_performance" <?php echo ($report_type == 'employee_performance') ? 'selected' : ''; ?>>Employee Performance Report</option>
                                            <option value="task_completion" <?php echo ($report_type == 'task_completion') ? 'selected' : ''; ?>>Task Completion Report</option>
                                            <option value="maintenance_summary" <?php echo ($report_type == 'maintenance_summary' || empty($report_type)) ? 'selected' : ''; ?>>Maintenance Summary Report</option>
                                        </select>
                                        <i class="fas fa-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Select the type of report you want to generate"></i>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Date Range</label>
                                    <div class="d-flex align-items-center">
                                        <div class="row g-2">
                                            <div class="col-md-6">
                                                <input type="date" class="form-control" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" placeholder="Start Date">
                                            </div>
                                            <div class="col-md-6">
                                                <input type="date" class="form-control" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" placeholder="End Date">
                                            </div>
                                        </div>
                                        <i class="fas fa-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Filter the report by date range (optional)"></i>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Employee (optional)</label>
                                    <div class="d-flex align-items-center">
                                        <select class="form-select" name="employee_id">
                                            <option value="">All Employees</option>
                                            <?php foreach ($employees as $employee): ?>
                                            <option value="<?php echo intval($employee['id']); ?>" <?php echo ($employee_id == $employee['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($employee['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <i class="fas fa-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Filter the report by specific employee (optional)"></i>
                                    </div>
                                </div>
                                <button type="submit" name="generate_report" class="btn btn-primary w-100"
                                        data-bs-toggle="tooltip" title="Generate the report based on your selected criteria">
                                    <i class="fas fa-download me-1"></i> Generate Report
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Export Options</h5>
                            <p class="text-muted small mb-3">Generate a report first, then use these export options</p>
                            <div class="d-grid gap-2">
                                <a href="#" class="btn btn-outline-danger export-btn" data-format="pdf"
                                   data-bs-toggle="tooltip" title="Export report as PDF document">
                                    <i class="fas fa-file-pdf me-1"></i> Export as PDF
                                </a>
                                <a href="#" class="btn btn-outline-primary export-btn" data-format="excel"
                                   data-bs-toggle="tooltip" title="Export report data as Excel spreadsheet">
                                    <i class="fas fa-file-excel me-1"></i> Export as Excel
                                </a>
                                <a href="#" class="btn btn-outline-secondary export-btn" data-format="csv"
                                   data-bs-toggle="tooltip" title="Export report data as CSV file">
                                    <i class="fas fa-file-csv me-1"></i> Export as CSV
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($report_data)): ?>
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Report Results</h5>
                    <div class="report-actions">
                        <a href="?download=1&format=csv&report_type=<?php echo urlencode($report_type); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&employee_id=<?php echo urlencode($employee_id); ?>" class="download-btn csv"
                           data-bs-toggle="tooltip" title="Download this report as CSV file">
                            <i class="fas fa-file-csv me-1"></i> Download CSV
                        </a>
                        <a href="?download=1&format=pdf&report_type=<?php echo urlencode($report_type); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&employee_id=<?php echo urlencode($employee_id); ?>" class="download-btn pdf"
                           data-bs-toggle="tooltip" title="Download this report as PDF document">
                            <i class="fas fa-file-pdf me-1"></i> Download PDF
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <?php 
                                    // Display table headers based on report type
                                    if (!empty($report_data)) {
                                        foreach (array_keys($report_data[0]) as $column) {
                                            echo "<th>" . ucwords(str_replace('_', ' ', $column)) . "</th>";
                                        }
                                    }
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <?php foreach ($row as $key => $value): ?>
                                    <td>
                                        <?php 
                                        // Format specific data types
                                        if (strpos($key, 'progress') !== false && is_numeric($value)) {
                                            echo '<div class="progress"><div class="progress-bar" role="progressbar" style="width: ' . $value . '%;" aria-valuenow="' . $value . '" aria-valuemin="0" aria-valuemax="100">' . $value . '%</div></div>';
                                        } elseif (strpos($key, 'status') !== false) {
                                            echo '<span class="badge status-' . strtolower(str_replace(' ', '', $value)) . '">' . $value . '</span>';
                                        } elseif (strpos($key, 'rate') !== false || strpos($key, 'percent') !== false) {
                                            echo $value . '%';
                                        } elseif (strpos($key, 'date') !== false && !empty($value)) {
                                            echo date('M j, Y', strtotime($value));
                                        } else {
                                            echo htmlspecialchars($value);
                                        }
                                        ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php elseif ($_SERVER['REQUEST_METHOD'] == 'POST'): ?>
            <div class="alert alert-info">
                No data found for the selected criteria.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="js/script.js"></script>
    <script>
        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Show loading overlay on form submission
            document.getElementById('reportForm').addEventListener('submit', function() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            });
            
            // Handle export buttons
            document.querySelectorAll('.export-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const format = this.getAttribute('data-format');
                    
                    if (format === 'excel') {
                        exportToExcel();
                    } else if (format === 'csv') {
                        // This will trigger the CSV download via the existing link
                        window.location.href = "?download=1&format=csv&report_type=<?php echo urlencode($report_type); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&employee_id=<?php echo urlencode($employee_id); ?>";
                    } else if (format === 'pdf') {
                        // This will trigger the PDF download via the existing link
                        window.location.href = "?download=1&format=pdf&report_type=<?php echo urlencode($report_type); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>&employee_id=<?php echo urlencode($employee_id); ?>";
                    }
                });
            });
            
            function exportToExcel() {
                // Get table data
                const table = document.querySelector('.table');
                const wb = XLSX.utils.table_to_book(table, {sheet: "Report"});
                XLSX.writeFile(wb, 'maintenance_report_<?php echo date('Y-m-d'); ?>.xlsx');
            }
        });
    </script>
</body>
</html>